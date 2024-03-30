<?php

namespace Drupal\recipe_finder\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\recipe_finder\Entity\FavoriteRecipe;
use Drupal\recipe_finder\FavoriteRecipeInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
    $this->guard();
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
    $uid = $this->requester->id();
    $paragraphs = $item->get("links")->referencedEntities();
    $links = [];
    foreach ($paragraphs as $paragraph) {
      $id = $paragraph->get("field_p_fr_recipe_id")->getString();
      $name = $paragraph->get("field_p_fr_recipe_name")->getString();
      $image_link = current($paragraph->get("field_p_fr_recipe_image")
        ->getValue());
      $links[] = [
        'id' => $id,
        'name' => $name,
        'image' => $image_link === FALSE ? NULL : $image_link,
      ];
    }
    return [
      'id' => $item->id(),
      'name' => $label,
      'links' => $links,
      'uid' => $uid,
    ];
  }

  public function post(Request $request): ModifiedResourceResponse {
    $this->guard();
    $raw_data = $request->getContent();
    $body = json_decode($raw_data, TRUE);
    if (empty($body['id']) || empty($body['name'])) {
      return new ModifiedResourceResponse(NULL, 400);
    }
    $favorite_recipe = $this->getCurrentUserFavoriteRecipe();
    if (!empty($this->isRecipeExist([$body['id']]))) {
      return new ModifiedResourceResponse($this->normalizerFavoriteRecipe($favorite_recipe), 200);
    }
    $uid = $this->requester->id();
    $paragraph = Paragraph::create([
      'type' => 'favorite_recipe',
      "field_p_fr_recipe_id" => $body['id'],
      "field_p_fr_recipe_name" => $body['name'],
      "field_p_fr_recipe_image" => $body['image'],
      'author' => $uid,
    ]);
    if ($favorite_recipe) {
      $favorite_recipe->get("links")->appendItem($paragraph);
    }
    else {
      $favorite_recipe = FavoriteRecipe::create([
        'label' => "Favorite recipe of " . $this->requester->getAccountName(),
        'links' => $paragraph,
        'author' => $uid,
      ]);
    }
    $paragraph->save();
    $favorite_recipe->save();
    return new ModifiedResourceResponse($this->normalizerFavoriteRecipe($favorite_recipe), 200);
  }

  public function delete(Request $request): ModifiedResourceResponse {
    $this->guard();
    $raw_data = $request->getContent();
    $body = json_decode($raw_data, TRUE);
    if (empty($body['id'])) {
      return new ModifiedResourceResponse(NULL, 400);
    }
    $favorite_recipe = $this->getCurrentUserFavoriteRecipe();
    if ($favorite_recipe) {
      $recipes = $this->isRecipeExist($body['id'], TRUE);
      foreach ($recipes as $recipe) {
        Paragraph::load($recipe->paragraph_id)?->delete();
      }
      $favorite_recipe->save();
      return new ModifiedResourceResponse($this->normalizerFavoriteRecipe($favorite_recipe), 200);
    }
    return new ModifiedResourceResponse(NULL, 400);
  }

  protected function isRecipeExist(array $recipe_id, bool $get_all = FALSE): array|bool|object {
    $uid = $this->requester->id();
    $database = \Drupal::database();
    $query = $database->select("favorite_recipe", "fr")
      ->where("fr.uid = :uid", [':uid' => $uid])
      ->condition('fr.status', '1')
      ->where("pifd.type = 'favorite_recipe'")
      ->where("p_fpfri.field_p_fr_recipe_id_value IN (:recipe_id[])", [':recipe_id[]' => $recipe_id]);
    $query->addField('p_fpfri', 'field_p_fr_recipe_id_value', 'recipe_id');
    $query->addField('pifd', 'id', 'paragraph_id');
    $query->leftJoin("paragraphs_item_field_data", "pifd", "pifd.parent_id = fr.id");
    $query->leftJoin("paragraph__field_p_fr_recipe_id", "p_fpfri", 'pifd.id = p_fpfri.entity_id');
    return $get_all ? $query->execute()->fetchAll() : $query->execute()
      ->fetch();
  }

  protected function guard(): void {
    $roles = $this->requester->getRoles();
    if (!in_array('authenticated', $roles)) {
      throw new AccessDeniedHttpException("Access denied");
    }
  }

}