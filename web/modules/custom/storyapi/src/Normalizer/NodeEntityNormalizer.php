<?php
namespace Drupal\storyapi\Normalizer;
use Drupal;
use DateTime;
use Drupal\Core\Render\Element\Value;
use Drupal\Core\Url;
use Drupal\serialization\Normalizer\ContentEntityNormalizer;
use Drupal\smart_date\Entity\SmartDateFormat;
use Drupal\Core\Datetime\DrupalDateTime;
/**
 * Converts the Drupal entity object structures to a normalized array.
 */
class NodeEntityNormalizer extends ContentEntityNormalizer {
  /**
 * The interface or class that this Normalizer supports.
 *
 * @var string
 */
  protected $supportedInterfaceOrClass = 'Drupal\node\NodeInterface';


  /**
   * Entity fields to be returned.
   *
   * @var array
   */
  protected $fields = [];
  protected $remap = [
    'nid' => 'id'
  ];
  protected $shifts = [
    'nid',
    'title',
    'created',
    'changed',
    'promote',
    'type',
    'field_body',
    'body',
    'field_description',
    'field_summary',
    'field_video',
    'field_person_title',
    'field_media_summary',

  ];
  protected $valueKeys = [
    'type' => 'target_id',

   // 'field_video' => 'uri',
    'field_venue_link' => 'uri',

    // Make sure the full object is returned.
    'field_event_date' => '_no_key_',
  ];
  protected $filters = [
    'field_video' => ['target_id', 'target_type', 'url'],
    'field_section_chapter' => ['target_id', 'target_type', 'url'],
    //'field_year_range' => ['value','end_value']
  ];
  protected $loadableNestedEntities = [
    'field_connected_people' => ['name' => 'value', 'path' => 'url()', 'id' => 'id'],
    'field_section_chapter' =>['name' => 'value', 'path' => 'url()', 'id' => 'id'],
    'field_files' => [],
    'field_audio' => [],
    'field_section_media' => [],
    'field_photos' => [],
    'field_video' => [],
    'field_headshot' => [],
    'field_person_title' => []
  ];

  protected $loadableNestedEntityShifts = [
    'field_video' => [],
    'field_year_range' => ['value', 'end_value'],


  ];

protected $transforms = [
    'field_person_title' => 'transformSingleArray',
    'field_year_range' => 'transformEventDateRange',
    'field_body' => 'transformBody',
    'field_photos'  => 'transformMedia',
    'field_video' => 'transformRemoteVideo',
    'field_files' => 'transformDocument',
    'field_audio' => 'transformDocument',
    'field_headshot' => 'transformMedia',
  ];
  /**
 * {@inheritdoc}
   */

  public function normalize($entity, $format = NULL, array $context = []) : float|array|\ArrayObject|bool|int|string|null {
    $attributes = parent::normalize($entity, $format, $context);
    $fields = $this->setFields($attributes)
      ->removeFields()
      ->applyFormats()
      ->filterFields()
      ->loadNestedEntityFields()
      ->transformFields()
      ->shiftFields()
      ->remapFields()
      ->addFields()
      ->groupFields()
      ->getFields();
      return $fields;
  }
   /**
   * Fields to remove from node output.
   *
   * @var array
   */
  protected $remove = [
    'uuid',
    'uid',
    'vid',
    'langcode',
    'default_langcode',
    'status',
    'sticky',
    'revision_timestamp',
    'revision_uid',
    'revision_log',
    'menu_link',
    'revision_translation_affected',
    'target_uuid',
    'field_section_media'
  ];
  /**
   * Remove some fields.
   */
  public function removeFields() {
    $this->fields = array_filter($this->fields, function ($key) {
      return !in_array($key, $this->remove);
    }, ARRAY_FILTER_USE_KEY);
    return $this;
  }
   /**
   * Set the fields on our object.
   */
  public function setFields($fields) {
    $this->fields = $fields;
    return $this;
  }
 public function getFields() {
    return $this->fields;
  }
  public function applyFormats() {

    $apply_format = function ($field, $key) {
      if (in_array($key, ['created', 'changed', 'field_year_range', "field_event_date_range"])) {
        //nothing
      } elseif (isset($field['format']) && isset($field['value'])) {
        $field['value'] = check_markup($field['value'], $field['format']);
      }
      if (isset($field['uri'])) {
        $field['uri'] = Url::fromUri($field['uri'])->toString();
      }
      return $field;
    };
   // dump($this->fields);
    $fields = $this->fields;
    $keys = array_keys($fields);

    $values = array_map(function ($key) use ($apply_format, $fields) {
      $field = $fields[$key];
      if (is_array($field)) {
        return array_map(function ($value) use ($apply_format, $key) {
          return $apply_format($value, $key);
        }, $field);
      }
      else {
        return $apply_format($field, $key);
      }
    }, $keys);

    $this->fields = array_combine($keys, $values);
   // dump($this->fields);
    return $this;
  }

  /**
   * Filter out properties on a certain fields.
   */
  public function filterFields() {
    foreach ($this->fields as $field => &$value) {
      if (isset($this->filters[$field])) {
        $this->fields[$field] = array_map(function ($values) use ($field) {
          return array_filter($values, function ($key) use ($field) {
            return in_array($key, $this->filters[$field]);
          }, ARRAY_FILTER_USE_KEY);
        }, $this->fields[$field]);
      }
    }
    return $this;
  }

  /**
   * Load nested entity fields.
   */
  public function loadNestedEntityFields() {
    $fields = array_intersect_key($this->fields, $this->loadableNestedEntities);
    foreach ($fields as $fieldname => &$values) {

      $ids = [];
      $target_type = NULL;
      $same_target_type = TRUE;
      $target_entities = [];

      $shifts = !empty($this->loadableNestedEntityShifts[$fieldname])
        ? $this->loadableNestedEntityShifts[$fieldname]
        : [];

      $values = array_values(array_filter($values, function ($v) {
        return !empty($v['target_type']);
      }));

      foreach ($values as $k => $value) {
        // Collect the IDs of existing entities to load, and directly grab the
        // "autocreate" entities that are already populated in $item->entity.
        if ($value['target_id'] !== NULL && !empty($value['target_type'])) {
          $ids[$k] = $value['target_id'];
          $same_target_type = $same_target_type && ($target_type === NULL || $target_type === $value['target_type']);
          $target_type = !empty($value['target_type']) ? $value['target_type'] : NULL;
        }
      }
      // Load and add nested entities.
      if ($ids && $same_target_type) {
        $entities = \Drupal::entityTypeManager()->getStorage($target_type)->loadMultiple($ids);
        $fieldMap = $this->loadableNestedEntities[$fieldname];
        foreach ($ids as $delta => $target_id) {
          if (isset($entities[$target_id])) {
            $entity = $entities[$target_id];
            if ($entity->hasField('status') && !$entity->status->value) {
              $target_entities[$delta] = NULL;
            }
            else {
              if (!empty($fieldMap)) {
                $target_entities[$delta] = $this->mapEntityFields($entity, $fieldMap, $shifts);
              }
              else {
                $target_entities[$delta] = method_exists($entity, 'toArray') ? $entity->toArray() : $entity;
              }
            }

          }
        }

        // Ensure the returned array is ordered by deltas.
        ksort($target_entities);
      }

      if ($target_entities) {
        foreach ($target_entities as $k => $entity) {
          $values[$k] = $entity;
        }
      }
    }
    $this->fields = array_merge($this->fields, $fields);
    return $this;
  }

  /**
   * Manually normalize nested elements.
   *
   * Instead of running nested elements through `->normalize()` (which breaks
   * things by overwriting the currently normalized entity, for some reason!),
   * do a pretty complicated field map.
   */
  public function mapEntityFields($entity, $map, $shifts = []) {
    return array_reduce(
      array_keys($map),
      function ($a, $k) use ($map, $entity, $shifts) {
        $f = $map[$k];
        $value = NULL;
        if ($f === 'id') {
          $value = (int) $entity->id();
        }
        elseif ($f === 'type()') {
          $value = $entity->getType();
        }
        elseif ($f === 'name()') {
          $value = $entity->getTitle();
        }
        elseif ($f === 'status()') {
          $value = !empty($entity->status) ? (int) $entity->status->value : 0;
        }
        elseif ($f === 'boolean()') {
          $field = $entity->hasField($k) ? $entity->get($k) : NULL;
          $v = !empty($field) ? $field->getValue() : NULL;
          $value = !empty($v) && $v[0] && !empty($v[0]['value']) && $v[0]['value'] !== '0'
            ? TRUE
            : FALSE;
        }
        elseif ($f === 'url()') {
          $value = $entity->toUrl()->toString();
        }
        elseif ($f === 'singleimage()') {
          $field = $entity->hasField($k) ? $entity->get($k) : NULL;
          if (!empty($field)) {
            $images = $field->getValue();
            $children = $field->referencedEntities();

            $value = [
              'url' => !empty($children[0]->uri->value)
                ?  	\Drupal::service('file_url_generator')->generateAbsoluteString($children[0]->uri->value)
                : NULL,
              'alt' => !empty($images[0]['alt']) ? $images[0]['alt'] : NULL,
            ];
          }
        }
        elseif ($f === 'mediaimage()') {
          $field = $entity->hasField($k) ? $entity->get($k) : NULL;
          if (!empty($field)) {
            $children = $field->referencedEntities();

            if(!empty($children))
            {
              $image = $children[0]->field_media_image->entity;
              $value = [
                'url' => !empty($image->uri->value)
                ? \Drupal::service('file_url_generator')->generateAbsoluteString($image->uri->value)
                : NULL,
                'alt' => !empty($children[0]->field_media_image->alt) ? $children[0]->field_media_image->alt : NULL,
              ];
           }
         }
        }
        elseif ($f === 'document()') {

          $field = $entity->hasField($k) ? $entity->get($k) : NULL;
          if (!empty($field)) {
            $doc = $field->referencedEntities()[0];
            if(!empty($doc))
            {
              $value = !empty($doc->uri->value)
                ? \Drupal::service('file_url_generator')->generateAbsoluteString($doc->uri->value)
                : NULL;
           }
         }
        }
        elseif (is_array($f)) {
          $field = $entity->hasField($k) ? $entity->get($k) : NULL;

          if (!empty($field) &&
            get_class($field) === 'Drupal\Core\Field\EntityReferenceFieldItemList'
          ) {
            $children = $field->referencedEntities();

            $value = array_map(function ($child) use ($f) {
              return $this->mapEntityFields($child, $f, []);
            }, $children);
          }
          else {
            $children = !empty($field) ? $field->getValue() : NULL;

            if (!empty($children)) {
              $value = array_map(function ($child) use ($f) {
                return array_map(function ($fieldkey) use ($child) {
                  if ($fieldkey === 'uri') {
                    $url = !empty($child['uri']) ? Url::fromUri($child['uri']) : NULL;
                    return !empty($url) ? $url->toString() : NULL;
                  }
                  return $child[$fieldkey] ?? NULL;
                }, $f);
              }, $children);
            }
            else {
              $value = NULL;
            }
          }
        }
        else {
          $field = $entity->hasField($k) ? $entity->get($k) : NULL;
          $value = !empty($field) && !empty($field->{$f}) ? $field->{$f} : NULL;
        }

        if (in_array($k, $shifts)) {
          $value = is_array($value) && !empty($value[0]) ? $value[0] : $value;
        }

        $shouldInclude = in_array($k, ['id', 'title', 'name', 'type', 'path'])
          || $entity->hasField($k);
        if ($shouldInclude && strpos($k, "field_") === 0) {
          $a['fields'][substr($k, 6)] = $value;
        }
        elseif ($shouldInclude) {
          $a[$k] = $value;
        }

        return $a;
      },
      []
    );
  }


  /**
   * Perform transformations on fields before returning.
   */
  public function transformFields() {
    foreach ($this->transforms as $fieldname => $method) {
      if (method_exists($this, $method) && isset($this->fields[$fieldname])) {
        foreach ($this->fields[$fieldname] as $i => $field) {
          $this->fields[$fieldname][$i] = $this->{$method}($field);
        }
      }
    }
    return $this;
  }

  /**
   * Shift first value from array of values and assign it directly.
   */
  public function shiftFields() {
    $shifts = array_intersect_key($this->fields, array_flip($this->shifts));
    foreach ($shifts as $field => &$value) {
      if (is_array($value) && isset($value[0][$this->getShiftKey($field)])) {
        $value = $value[0][$this->getShiftKey($field)];
      }
      elseif (isset($value[0])) {
        $value = $value[0];
      }
      else {
        $value = FALSE;
      }
    }
    $this->fields = array_merge($this->fields, $shifts);
    return $this;
  }

  /**
   * Remap fields names.
   */
  public function remapFields() {
    $this->fields = array_reduce(array_keys($this->fields), function ($fields, $field) {
      $newfield = isset($this->remap[$field]) ? $this->remap[$field] : $field;
      $fields[$newfield] = $this->fields[$field];
      return $fields;
    }, []);
    return $this;
  }

  /**
   * Group all fields under the group.
   */
  public function groupFields() {
    $this->fields = array_reduce(array_keys($this->fields), function ($fields, $field) {
      if (strpos($field, "field_") === 0) {
        $fields['fields'][substr($field, 6)] = $this->fields[$field];
      }
      else {
        $fields[$field] = $this->fields[$field];
      }
      return $fields;
    }, []);
    return $this;
  }

  /**
   * Add some custom fields.
   */
  public function addFields() {
    //dump($this->fields);
    $this->fields['path'] = Drupal::service('path_alias.manager')->getAliasByPath('/node/' . $this->fields['id']);
    return $this;
  }

  /**
   * Get the shift key.
   */
  public function getShiftKey($field) {
    return isset($this->valueKeys[$field]) ? $this->valueKeys[$field] : 'value';
  }

  /**
   * Perform transformations on fields before returning.
   */
  public function transformFieldTableDates($field) {
    $caption = $field['caption'];
    unset($field['caption']);
    unset($field['value']['caption']);

    $value = $field['value'];

    //Checking for errant value issue
    if(!empty($value) && count($value) > 0)
    {
      $result = call_user_func_array('array_merge', $value);
      unset($result['weight']);
      $values = implode("", $result);
      if($values == NULL)
        return NULL;
    }
    return [
      'rows' => !empty($field['value']) ? $field['value'] : NULL,
      'caption' => $caption,
    ];
  }

  public function transformEventDateRange($field)
  {
    return array_merge($field, [
      'value'       => $field['value'],
      'end_value'   => $field['end_value'],
      'start_year'  => (new DateTime($field['value']))->format('Y'),
      'end_year'    => (new DateTime($field['end_value']))->format('Y'),
      'start_date'  => (new DateTime($field['value']))->format('Y-m-d'),
      'end_date'    => (new DateTime($field['end_value']))->format('Y-m-d'),
      'start_time'  => (new DateTime($field['value']))->format('H:i:sO'),
      'end_time'    => (new DateTime($field['end_value']))->format('H:i:sO'),
      'timezone'    => $field['timezone']
    ]
    );
  }
  /**
   * Perform transformations on fields before returning.
   */
  public function transformFieldSection($field) {
    return [
      'meta' => [
        'id' => !empty($field['id']) ? $field['id'] : NULL,
        'type' => !empty($field['type']) ? $field['type'] : NULL
      ],
      'body' => !empty($field['fields']['body'][0]['value'])
        ? check_markup($field['fields']['body'][0]['value'], $field['fields']['body'][0]['format'])
        : NULL,
      'label' => !empty($field['fields']['label']) ? $field['fields']['label'] : NULL,
    ];
  }
  /**
   * Perform transformations on fields before returning.
   */
  public function transformSingleArray($field) {
    dump($field);
  }

  public function getNode($nid)
  {
    $node = \Drupal\node\Entity\Node::load($nid);
    return $node;
  }
  public function transformMedia($field){
  //  array_push($this->debug, $field);

    if(is_array($field) && array_key_exists("field_media_image", $field))
    {
      $image = $field['field_media_image'][0];
      $fid = $image['target_id'];
      $file = \Drupal\file\Entity\File::load($fid);
      $alt = $image['alt'];
    }
    else return null;
    $value = [
      'id' => !empty($fid) ? $fid : NULL,
      'url' => !empty($file->uri->value)
      ? \Drupal::service('file_url_generator')->generateAbsoluteString($file->uri->value)
      : NULL,
      'alt' => !empty($alt) ? $alt : NULL,
      'caption' => !empty($field['field_caption'][0]['value']) ? $field['field_caption'][0]['value'] : NULL,
      'credit' => !empty($field['field_credit'][0]['value']) ? $field['field_credit'][0]['value'] : NULL
    ];
    return $value;
  }
  public function transformRemoteVideo($field) {
    $video = null;
    if(is_array($field))
     {
       if(array_key_exists("field_media_oembed_video", $field))
       {
         $video = $field['field_media_oembed_video'][0];
       }
       return $video['value'];
    }


  }
  public function transformDocument($field){
     $file = null;
     if(is_array($field))
      {
        if(array_key_exists("field_media_document", $field))
        {
          $file = $field['field_media_document'][0];
        }
        elseif(array_key_exists("field_media_audio_file", $field)) {
          $file = $field['field_media_audio_file'][0];
        }
        $fid = $file['target_id'];
        $file = \Drupal\file\Entity\File::load($fid);
        $description = $field['name'][0]['value'];
      }
      $value = [
        'id' => !empty($fid) ? $fid : NULL,
        'url' => !empty($file->uri->value)
        ? \Drupal::service('file_url_generator')->generateAbsoluteString($file->uri->value)
        : NULL,
        'description' => !empty($description) ? $description : NULL,
        'summary' => !empty($field['field_summary'][0]['value']) ? $field['field_summary'][0]['value'] : NULL,
        'name' => !empty($field['name'][0]['value']) ? $field['name'][0]['value'] : NULL,
      ];
      return $value;
    }
  /**
   * Perform transformations on fields before returning.
   */
  public function transformBody($field) {
    $str = !empty($field['value']) ? (string) $field['value'] : '';
    $str = preg_replace(
      '/(<iframe .*?src="https?:\/\/(www\.)?[A-Za-z0-9\/._\-#?]+".*?(\/>|><\/iframe>))/i',
      "<span class=\"vidwrap\">$0</span>",
      $str
    );

    $markup = new \Drupal\Core\Render\Markup();
    $markup = $markup->create($str);

    return array_merge($field, [
      'value' => $markup
    ]);
  }

}
/* End of class. */