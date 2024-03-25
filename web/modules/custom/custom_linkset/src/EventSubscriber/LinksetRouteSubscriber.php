<?php
namespace Drupal\custom_linkset\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\system\Routing\MenuLinksetRoutes;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouteCollection;

class LinksetRouteSubscriber extends MenuLinksetRoutes {

  public function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get("system.menu.linkset")) {
      $route->setDefault("_controller", "\Drupal\custom_linkset\Controller\CustomLinkSetController::process");
    }
  }

}