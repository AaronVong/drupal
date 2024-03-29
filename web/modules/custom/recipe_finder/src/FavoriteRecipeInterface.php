<?php

declare(strict_types=1);

namespace Drupal\recipe_finder;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a favorite recipe entity type.
 */
interface FavoriteRecipeInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
