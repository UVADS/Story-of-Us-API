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

if($id) {

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
}
else {
  $nodes =\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree("person");
  $person = \Drupal\taxonomy\Entity\Term::loadMultiple(array_column($nodes, 'tid'));
  $person = Drupal::service('serializer')->normalize($person);


  foreach ($terms as $term) {
    $person[] = Drupal::service('serializer')->normalize($term);
  }
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

if($id) {
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
}
else
{
  $nodes =\Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree("topics");
  $topic = \Drupal\taxonomy\Entity\Term::loadMultiple(array_column($nodes, 'tid'));
  $topic = Drupal::service('serializer')->normalize($topic);


  foreach ($terms as $term) {
    $topic[] = Drupal::service('serializer')->normalize($term);
  }
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



}
