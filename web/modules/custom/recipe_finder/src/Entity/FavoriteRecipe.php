<?php

declare(strict_types=1);

namespace Drupal\recipe_finder\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\link\LinkItemInterface;
use Drupal\recipe_finder\FavoriteRecipeInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the favorite recipe entity class.
 *
 * @ContentEntityType(
 *   id = "favorite_recipe",
 *   label = @Translation("Favorite Recipe"),
 *   label_collection = @Translation("Favorite Recipes"),
 *   label_singular = @Translation("favorite recipe"),
 *   label_plural = @Translation("favorite recipes"),
 *   label_count = @PluralTranslation(
 *     singular = "@count favorite recipes",
 *     plural = "@count favorite recipes",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\recipe_finder\FavoriteRecipeListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\recipe_finder\FavoriteRecipeAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\recipe_finder\Form\FavoriteRecipeForm",
 *       "edit" = "Drupal\recipe_finder\Form\FavoriteRecipeForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "favorite_recipe",
 *   admin_permission = "administer favorite_recipe",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/favorite-recipe",
 *     "add-form" = "/favorite-recipe/add",
 *     "canonical" = "/favorite-recipe/{favorite_recipe}",
 *     "edit-form" = "/favorite-recipe/{favorite_recipe}/edit",
 *     "delete-form" = "/favorite-recipe/{favorite_recipe}/delete",
 *     "delete-multiple-form" = "/admin/content/favorite-recipe/delete-multiple",
 *   },
 *   field_ui_base_route = "entity.favorite_recipe.settings",
 * )
 */
final class FavoriteRecipe extends ContentEntityBase implements FavoriteRecipeInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['links'] = BaseFieldDefinition::create("string")
      ->setLabel("Favorite Recipe Links")
      ->setDisplayOptions('form', [
        'label' => 'above',
        'weight' => -5,
        'type' => 'link',
      ])
      ->setSettings(['link_type' => LinkItemInterface::LINK_GENERIC, 'title' => DRUPAL_OPTIONAL])
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $fields['belong_to_user'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Belong to user'))
      ->setSetting('target_type', 'user')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 0,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the favorite recipe was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the favorite recipe was last edited.'));
    return $fields;
  }

}
