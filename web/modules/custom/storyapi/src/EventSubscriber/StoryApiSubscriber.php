<?php

namespace Drupal\storyapi\EventSubscriber;

use Drupal;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\node\Entity\Node;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class StoryApiSubscriber implements EventSubscriberInterface {
  /**
   * Check for redirection.
   *
   * @param  GetResponseEvent $event
   */
  public function onRequest(RequestEvent $event) {
    $request = $event->getRequest();
    $node = $request->attributes->get('node');
    $path = $request->getPathInfo();

    if ($node instanceof Node &&
      !preg_match('/\/(edit|delete|revisions)\/?$/', $path) &&
      !preg_match('/^\/clone\/[0-9]+\/quick_clone?$/', $path) &&
      !$request->query->get('_format')
    ) {
      $config = Drupal::config('dsiapi.settings');
      if ($config->get('redirect_enabled')) {
        Drupal::service('page_cache_kill_switch')->trigger();
        $event->setResponse(new TrustedRedirectResponse($config->get('redirect_location') . $path));
      }
    }
  }

  /**
   * Modify the response on the way out the door.
   *
   * @param  FilterResponseEvent $event
   */
  public function onRespond(ResponseEvent $event) {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $event->getRequest();
    $response = $event->getResponse();
    $path = $request->getPathInfo();
    if (preg_match("/^\/admin\/loginauth/", $path)) {
      $response->headers->remove('X-Frame-Options');
      $response->headers->set('Access-Control-Allow-Origin', '*');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequest'];
    $events[KernelEvents::RESPONSE][] = ['onRespond', -10];
    return $events;
  }
}
