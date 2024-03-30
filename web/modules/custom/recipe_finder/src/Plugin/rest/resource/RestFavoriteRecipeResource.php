<?php

namespace Drupal\recipe_finder\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\recipe_finder\Entity\FavoriteRecipe;
use Drupal\recipe_finder\FavoriteRecipeInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a resource for database watchdog log entries.
 *
 * @RestResource(
 *   id = "rest_favorite_recipe_resource",
 *   label = @Translation("Rest resource for favorite recipe"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/favorite-recipe",
 *     "create" = "/api/v1/add/favorite-recipe"
 *   }
 * )
 */
class RestFavoriteRecipeResource extends ResourceBase {

  protected AccountInterface $requester;

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->requester = $container->get('current_user');
    $instance->entityTypeManager = $container->get("entity_type.manager");

    return $instance;
  }

  public function get(Request $request): ModifiedResourceResponse {
    $data = NULL;
    $status_code = 404;
    if ($favorite_recipe = $this->getCurrentUserFavoriteRecipe()) {
      $data = $this->normalizerFavoriteRecipe($favorite_recipe);
      $status_code = 200;
    }
    return new ModifiedResourceResponse($data, $status_code);
  }

  protected function getCurrentUserFavoriteRecipe(): ?FavoriteRecipeInterface {
    $uid = $this->requester->id();
    $favorite_recipe_list = $this->entityTypeManager->getStorage('favorite_recipe')
      ->loadByProperties(['uid' => $uid]);
    if ($favorite_recipe = current($favorite_recipe_list)) {
      return $favorite_recipe;
    }
    return NULL;
  }

  function normalizerFavoriteRecipe(FavoriteRecipeInterface $item): array {
    $label = $item->label();
    $uid = current(($item->get('belong_to_user')->getValue()))['target_id'];
    $links = $item->get('links')->getValue();
    return [
      'title' => $label,
      'uid' => $uid,
      'links' => $links,
      'id' => $item->id(),
    ];
  }

  public function post(Request $request): ModifiedResourceResponse {
    $raw_data = $request->getContent();
    $body = json_decode($raw_data);
    $favorite_recipe = $this->getCurrentUserFavoriteRecipe();
    $uid = $this->requester->id();
    if ($favorite_recipe) {
      $links = $favorite_recipe->get('links')->getValue();
      $uri_columns = array_column($links, 'uri');
      if (!in_array($body->uri, $uri_columns)) {
        $links[] = [
          'uri' => $body->uri,
          'title' => $body->title,
        ];
        $favorite_recipe->set('links', $links);
        $favorite_recipe->save();
      }
    }
    else {
      $favorite_recipe = FavoriteRecipe::create([
        'title' => "Favorite recipe of " . $this->requester->getAccountName(),
        'belong_to_user' => $uid,
        'links' => [
          'uri' => $body->uri,
          'title' => $body->title,
        ],
        'author' => $uid,
      ]);
      $favorite_recipe->save();
    }
    return new ModifiedResourceResponse($this->normalizerFavoriteRecipe($favorite_recipe), 200);
  }

  public function delete(Request $request): ModifiedResourceResponse {
    $raw_data = $request->getContent();
    $body = json_decode($raw_data);
    $favorite_recipe = $this->getCurrentUserFavoriteRecipe();
    if ($favorite_recipe) {
      $links = $favorite_recipe->get('links')->getValue();
      $uri_columns = array_column($links, 'uri');
      if ($index = array_search($body->uri, $uri_columns) !== FALSE) {
        unset($links[$index]);
        $favorite_recipe->set('links', $links);
        $favorite_recipe->save();
      }
      return new ModifiedResourceResponse($this->normalizerFavoriteRecipe($favorite_recipe), 200);
    }
    return new ModifiedResourceResponse(NULL, 400);
  }

}