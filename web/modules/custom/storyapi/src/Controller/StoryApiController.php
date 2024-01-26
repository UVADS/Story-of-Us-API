<?php

namespace Drupal\storyapi\Controller;

use Drupal;
use DateTime;
use DateInterval;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\Datetime\DrupalDateTime;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use \Drupal\Core\Controller\ControllerBase;
use \Drupal\file\FileInterface;
use \Drupal\media\Entity\Media;
use \Drupal\Core\Entity\Display\EntityFormDisplayInterface;


/**
 * Controller class for homepage routes.
 */
class StoryApiController extends ControllerBase{

  /**
   * Return the login authorization.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   HTML page.
   */
  public function loginauth() {
    $response = new Response();
    $loginauth = Drupal::currentUser()->hasPermission('administer nodes') ? '1' : '0';
    $response->setContent("<!DOCTYPE html><html data-loginauth=\"$loginauth\"><head></head><body><script src=\"/modules/custom/dsiapi/js/loginauth.js\"></script></body></html>");
    return $response;
  }

  /**
   * Return the cachekey.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function cachekey() {
    $config = Drupal::config('dsiapi.settings');
    $response = new JsonResponse();
    $response->headers->set('Access-Control-Allow-Origin', '*');
    $response->setData(['key' => $config->get('cache_key')]);
    return $response;
  }


  /**
   * Get stories.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function sections($id = null) {
    $page = \Drupal::request()->query->get('page') ? (int) \Drupal::request()->query->get('page') : 0;
    $perpage = \Drupal::request()->query->get('perpage') ? (int) \Drupal::request()->query->get('perpage') : 15;


    $query = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
      ->condition('type', 'section')
      ->condition('status', 1)
      ->sort('field_year_range', 'ASC');

    $query->condition('field_section_chapter.target_id', $id, '=');

    $queryClone = clone $query;
    $total = $queryClone->count()->execute();

    $nids = $query->pager($perpage)->execute();
    $nodes = Node::loadMultiple($nids);

    $count = count($nodes);
    $stories = [];
    foreach ($nodes as $node) {
      $stories[] = Drupal::service('serializer')->normalize($node);
    }

    return new JsonResponse([
      'results' => $stories,
      'count' => $count,
      'meta' => [
        'page' => $page,
        'perpage' => $perpage,
        'total' => $total,
        'totalpages' => ceil($total / $perpage),
      ],
    ]);
  }

public function person($id = null) {
  $page = \Drupal::request()->query->get('page') ? (int) \Drupal::request()->query->get('page') : 0;
  $perpage = \Drupal::request()->query->get('perpage') ? (int) \Drupal::request()->query->get('perpage') : 4;


  $person = \Drupal\taxonomy\Entity\Term::load($id);
  $person = Drupal::service('serializer')->normalize($person);

  $query = \Drupal::entityQuery('node')
  ->accessCheck(TRUE)
  ->condition('type', 'section')
  ->condition('status', 1)
  ->sort('field_year_range', 'ASC');

$query->condition('field_connected_people.target_id', $id, 'IN');
$queryClone = clone $query;
$total = $queryClone->count()->execute();

$nids = $query->pager($perpage)->execute();
$nodes = Node::loadMultiple($nids);

$count = count($nodes);
$sections = [];
foreach ($nodes as $node) {
  $sections[] = Drupal::service('serializer')->normalize($node);
}

return new JsonResponse([
  'person' => $person,
  'sections' => $sections,
  'count' => $count,
  'meta' => [
    'page' => $page,
    'perpage' => $perpage,
    'total' => $total,
    'totalpages' => ceil($total / $perpage),
  ],
]);
}

public function topic($id = null) {
  $page = \Drupal::request()->query->get('page') ? (int) \Drupal::request()->query->get('page') : 0;
  $perpage = \Drupal::request()->query->get('perpage') ? (int) \Drupal::request()->query->get('perpage') : 4;


  $topic = \Drupal\taxonomy\Entity\Term::load($id);
  $topic = Drupal::service('serializer')->normalize($topic);

  $query = \Drupal::entityQuery('node')
  ->accessCheck(TRUE)
  ->condition('type', 'section')
  ->condition('status', 1)
  ->sort('field_year_range', 'ASC');

$query->condition('field_topics.target_id', $id, 'IN');
$queryClone = clone $query;
$total = $queryClone->count()->execute();

$nids = $query->pager($perpage)->execute();
$nodes = Node::loadMultiple($nids);

$count = count($nodes);
$sections = [];
foreach ($nodes as $node) {
  $sections[] = Drupal::service('serializer')->normalize($node);
}

return new JsonResponse([
  'topic' => $topic,
  'sections' => $sections,
  'count' => $count,
  'meta' => [
    'page' => $page,
    'perpage' => $perpage,
    'total' => $total,
    'totalpages' => ceil($total / $perpage),
  ],
]);
}

  /**
   * Get story_tags for admissions.
   * Would like to refactor this in the future.
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function storyTags() {

    $query = \Drupal::entityQuery('node')
    ->accessCheck(TRUE)
      ->condition('type', 'story')
      ->condition('status', 1)
      ->condition('field_story_department.target_id', 207, '=')
      ->sort('created', 'DESC');

    $nids = $query->execute();
    $nodes = Node::loadMultiple($nids);

    $tagIDs = [];
    $tags = [] ;
    $stories = [];
    foreach ($nodes as $node) {
      $stories[] = Drupal::service('serializer')->normalize($node);
    }
    foreach($stories as $story) {
      foreach($story['fields']['story_tags'] as $tag)
      {
        $id = $tag['id'];
        if (!in_array($id, $tagIDs)){
          $tagIDs[] = $id;
          $name = $tag['name'];
          unset($tag['name']);
          unset($tag['path']);
          $tag['name'] = $name;
          $tags[] = $tag;
        }
      }
    }
    $count = count($tags);

    return new JsonResponse([
      'results' => $tags,
      'count' => $count
    ]);
  }
  /**
   * Get events.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response.
   */
  public function events2() {
    $page = \Drupal::request()->query->get('page') ? (int) \Drupal::request()->query->get('page') : 0;
    $perpage = \Drupal::request()->query->get('perpage') ? (int) \Drupal::request()->query->get('perpage') : 4;

    $query = \Drupal::entityQuery('node')
    ->accessCheck(TRUE)
      ->condition('type', 'event')
      ->condition('status', 1);

    // Only include promoted (to home) Stories, if filter specified.
    $is_promoted = \Drupal::request()->query->get('is_promoted');
    if ($is_promoted) {
      $query->condition('field_promote_homepage', 1);
    }

    // Only include Events that include the specified filters.
    $filters = \Drupal::request()->query->get('tag_filters');
    if ($filters) {
      $filters = explode(',', $filters);
      $group = $query->orConditionGroup();
      foreach ($filters as $f) {
        $group->condition('field_event_tags', $f);
      }
      $query->condition($group);
    }

    // Exclude a list of Events, based on a comma-separated list of IDs.
    $exclude = \Drupal::request()->query->get('exclude');
    if ($exclude) {
      $excludeList = explode(',', $exclude);
      $query->condition('nid', $excludeList, 'NOT IN');
    }

    $is_past = \Drupal::request()->query->get('is_past');
    if ($is_past) {
      // Past: event start date/time less than now.
      $now = new DrupalDateTime('now');
      $now = $now->setTimezone(new \DateTimeZone('UTC'));

      $query->condition('field_event_date', $now->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), '<=');
      $query->sort('field_event_date', 'DESC');
    } else {
      // Upcoming: event ends today or later.
      $midnight = new DrupalDateTime('midnight');
      $midnight = $midnight->setTimezone(new \DateTimeZone('UTC'));

      $query->condition('field_event_date.end_value', $midnight->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT), '>=');
      $query->sort('field_event_date', 'ASC');
    }

    $queryClone = clone $query;
    $total = $queryClone->count()->execute();

    $nids = $query->pager($perpage)
      ->execute();

    $nodes = Node::loadMultiple($nids);

    $count = count($nodes);
    $events = [];
    foreach ($nodes as $node) {
      $events[] = Drupal::service('serializer')->normalize($node);
    }

    return new JsonResponse([
      'results' => $events,
      'count' => $count,
      'meta' => [
        'page' => $page,
        'perpage' => $perpage,
        'total' => $total,
      ],
    ]);
  }


  /**
   * Get featured Events, News and Projects by type.
   */
  public function getFeaturedByType($key) {
    $map = ['events' => 186, 'people' => 185, 'projects' => 184, 'stories' => 187];
    $tid = !empty($map[$key]) ? $map[$key] : NULL;

    // Conditionally include featured nodes.
    $results = [];
    if ($tid) {
      $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);

      if (!empty($term->get('field_featured'))) {
        $references = $term->get('field_featured');
        foreach ($references as $i => $ref) {
          $node = $ref->get('entity')->getTarget()->getValue();
          $results[] = Drupal::service('serializer')->normalize($node);
        }
      }
    }

    return new JsonResponse($results);
  }

  /**
   * Get Events, News and Projects by category.
   */
  public function getByCategory($tid) {
    $tid = $tid ? (int) $tid : NULL;

    $page = \Drupal::request()->query->get('page') ? (int) \Drupal::request()->query->get('page') : 0;
    $perpage = \Drupal::request()->query->get('perpage') ? (int) \Drupal::request()->query->get('perpage') : 10;

    $res = [];
    $total = 0;

    if ($tid) {
      $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);

      // Retrieve regular results.
      $query = \Drupal::entityQuery('node')
      ->accessCheck(TRUE)
        ->condition('type', ['event', 'project'], 'IN')
        ->condition('status', 1)
        ->sort('created', 'DESC');

      $group = $query->orConditionGroup()
        ->condition('field_event_category', $tid)
        ->condition('field_project_theme', $tid);
      $query->condition($group);

      $queryClone = clone $query;
      $total = $queryClone->count()->execute();

      $nids = $query->pager($perpage)
        ->execute();

      $nodes = Node::loadMultiple($nids);

      $res = [];
      foreach ($nodes as $node) {
        $res[] = Drupal::service('serializer')->normalize($node);
      }

      // Retrieve featured results.
      $featured = [];
      if (!empty($term->get('field_people'))) {
        $people = $term->get('field_people');
        foreach ($people as $i => $person) {
          $expert = $person->get('entity')->getTarget()->getValue();
          $featured[] = Drupal::service('serializer')->normalize($expert);
        }
      }
    }

    return new JsonResponse([
      'featured' => !empty($featured) ? $featured : NULL,
      'results' => $res,
      'meta' => [
        'page' => $page,
        'perpage' => $perpage,
        'total' => $total,
      ],
    ]);
  }
 /**
   * Builds the response.
   */
  public function build() {
    $output = '';
    $fields =  [ 'field_attachments_media' => ['news', 'story', 'page','project', 'event', 'degree', 'degree_details', 'microsite_page'],
               'field_cv_media' => ['person'],
               'field_syllabus_media' => ['dh_course']
              ];

    foreach($fields as $field => $types) {
      $output .= "<h3>Field: {$field}</h3>";
      foreach($types as $type)
      {
        $output .= "<h4>Field: {$type}</h4>";
        $output .= $this->convertNode2Media($type, $field, str_replace("_media", "", $field)) . "<Br>";
      }
    }
    $build['content'] = [
      '#type' => 'item',
      '#markup' => $output,
    ];

    return $build;
  }
  public function convertImages() {
    $output = '';

    $fields = ['field_event_image_media' => ['event'],
    'field_headshot_media' => ['person'],
    'field_hero_images' => ['homepage'],
    'field_image_media' => ['degree', 'homepage', 'landing_page', 'microsite',
     'microsite_page', 'news', 'page', 'person', 'project', 'news'],
         'field_project_image_media' => ['project'],
    'field_teaser_image_media' => ['page', 'degree', 'event', 'microsite', 'landing_page',
     'microsite_page', 'news', 'project', 'degree details', 'newscover', 'news'],
    'field_image_media' => ['link_block', 'cta', 'image', 'link_card',
      'person_card', 'slide', 'testimonial'],
    'field_statistic_media' => ['grid_item', 'statistic']];


    foreach($fields as $field => $types) {
      $output .= "<h3>Field: {$field}</h3>";
      foreach($types as $type)
      {
        $output .= "<h4>Field: {$type}</h4>";
        $output .= $this->convertNode2Media($type, $field, str_replace("_media", "", $field)) . "<Br>";
      }
    }

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $output,
    ];

    return $build;
  }
  public function sdsTeam($tid){
    $tid = $tid ? (int) $tid : NULL;

    $page = \Drupal::request()->query->get('page') ? (int) \Drupal::request()->query->get('page') : 0;
    $perpage = \Drupal::request()->query->get('perpage') ? (int) \Drupal::request()->query->get('perpage') : 10;

    $res = [];
    $total = 0;
    if($tid){
    $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);

      // Retrieve regular results.
      $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery()
      ->accessCheck(TRUE)
      ->condition('field_person_team', $tid)
      ->sort('field_last_name', 'ASC')
      ->sort('field_first_name', 'ASC');
      $nids = $query->execute();
      $nodes = Node::loadMultiple($nids);
      $res = [];

    foreach ($nodes as $node) {
      $res = Drupal::service('serializer')->normalize($node);
    }
    $count = count($nodes);
    $team = [];
    foreach ($nodes as $node) {
      $team[] = Drupal::service('serializer')->normalize($node);
    }

    return new JsonResponse([
      'results' => $team,
      'count' => $count,
      'meta' => [
        'page' => $page,
        'perpage' => $perpage,
        'total' => $count,
      ],
    ]);
    }
   }
  private function convertNode2Media($type, $new_field, $old_field){
	 $cnt = 0;
   $node_has_field = false;

	 $nids = \Drupal::entityQuery('node')->condition('type',$type)->execute();
	 $nodes = Node::loadMultiple($nids);
   if(!$node_has_field)
   {
     $node = array_values($nodes)[0];
     $entity = \Drupal\node\Entity\Node::load($node->id());
     if(!$entity->hasField($new_field))
     {
       $this->CreateAttachmentField($type, $new_field, $old_field);
       \Drupal::logger('dbmaint-Node2Media')->notice(
        'Attachment field added to @type.',
        ['@type' => $type]
      );
     }
     $node_has_field = true;
     $nodes = Node::loadMultiple($nids);
   }
	 foreach ($nodes as $node){
		 try{
			 $attachments = [];
			 foreach ($node->get($old_field) as $attachment){
				$attachments[] = ['id' => $attachment->entity->id(), 'desc' => $attachment->description];
			 }
       !empty($attachments) && dump($attachments);
       $documents = [];
       foreach ($attachments as $attachment) {
          $fid = $attachment["id"];
          $desc = $attachment['desc'];
          $file = \Drupal\file\Entity\File::load($fid);
          $result = \Drupal::service('file.usage')->listUsage($file);
          $filename = $file->get('filename')->value;
          $output .= "Creating media for {$filename}<br>.";
          $media_entity = Media::create([
              'bundle'     => 'document',
              'type'            => 'document',
              'uid'              	=> \Drupal::currentUser()->id(),
              'status' 			=> '1',
              'name'				=>  $desc,
              'field_media_document' => [
                'target_id' => $fid,
                'title' =>  !empty($file->title) ? $file->title : $filename
                ],
            ]);
          $media_entity->save();
          $documents[] = $media_entity;

       }

        if (!empty($documents))
        {
          $node = \Drupal\node\Entity\Node::load($node->id());
			    $node->set($new_field, $documents);
          $node->save();
  			  \Drupal::logger('dbmaint-Node2Media')->notice(
	  			  'Node "@node" converted to Media Entity "@media" Count "@cnt"',
		  		  ['@node' => $node->id(), '@media' => $media_entity->id(), "@cnt" => $cnt]
			    );
			    $cnt ++;
        }
      }
		 catch (Exception $e) {
              \Drupal::logger('dbmaint-Node2Media')->error($e->getMessage());
          }

	  }

	  return $cnt.' nodes converted';

  }
  private function CreateAttachmentField($type, $field, $old_field)
  {
    $output = "Creating Attachment Field {$field} on {$type}.";
    $storage = \Drupal\field\Entity\FieldStorageConfig::loadByName('node', $field);
    if(!$storage) {
      $output .= "Creating Field Storage";
      \Drupal\field\Entity\FieldStorageConfig::create(array(
        'field_name' => $field,
        'bundle' => $type,
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'cardinality' => \Drupal\Core\Field\FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
        'settings' => ['target_type' => 'media']
      ))->save();
    }
    else
    {
      dump($storage->setSettings(array('target_type' => 'media')));

    }
    \Drupal\field\Entity\FieldConfig::create([
      'field_name' => $field,
      'entity_type' => 'node',
      'field_type' => 'entity_reference',
      'type' => $type,
      'bundle' => $type,
      'label' => 'Attachments',
      'cardinality' => \Drupal\Core\Field\FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'handler' => 'default:media',
        'handler_settings' => [
          'target_bundles' => ['document' => 'document'],
          'sort' => [
            'field' => '_none',
            'direction' => 'ASC',
          ],
          'auto_create' => FALSE,
          'auto_create_type' => '',
        ],
      ],
    ])->save();
    \Drupal::service('entity_display.repository')->getViewDisplay('node', $type, 'default')->setComponent($field, array())->save();
    \Drupal::service('entity_display.repository')->getFormDisplay('node', $type, 'default')
    ->setComponent($field, array(
    'type' => 'media_library_widget'))->save();

    return $output;
  }
}
