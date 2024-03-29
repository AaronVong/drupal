<?php

declare(strict_types=1);

namespace Drupal\recipe_finder;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the favorite recipe entity type.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 *
 * @see https://www.drupal.org/project/coder/issues/3185082
 */
final class FavoriteRecipeAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    return match($operation) {
      'view' => AccessResult::allowedIfHasPermissions($account, ['view favorite_recipe', 'administer favorite_recipe'], 'OR'),
      'update' => AccessResult::allowedIfHasPermissions($account, ['edit favorite_recipe', 'administer favorite_recipe'], 'OR'),
      'delete' => AccessResult::allowedIfHasPermissions($account, ['delete favorite_recipe', 'administer favorite_recipe'], 'OR'),
      default => AccessResult::neutral(),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    return AccessResult::allowedIfHasPermissions($account, ['create favorite_recipe', 'administer favorite_recipe'], 'OR');
  }

}
