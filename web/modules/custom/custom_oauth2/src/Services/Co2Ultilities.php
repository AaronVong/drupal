<?php

namespace Drupal\custom_oauth2\Services;

use Drupal\Core\Entity\EntityConstraintViolationList;
use Drupal\Core\Entity\EntityConstraintViolationListInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\Validator\ConstraintViolation;

class Co2Ultilities {

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $current_user;

  public function __construct(AccountProxyInterface $current_user) {
    $this->current_user = $current_user;
  }

  public function getViolationMessages(EntityConstraintViolationList|EntityConstraintViolationListInterface $violation_list, array $ignore_fields = []): array {
    if (empty($violation_list)) {
      return [];
    }
    $messages = [];
    /**
     * @var ConstraintViolation $violation
     */
    foreach ($violation_list as $violation) {
      $invalid_value = $violation->getInvalidValue();
      $field_name = strtok($violation->getPropertyPath(), '.');
      if (in_array($field_name, $ignore_fields)) {
        continue;
      }
      $label = "";
      if ($invalid_value instanceof FieldItemList) {
        $field_definition = $invalid_value->getFieldDefinition();
        $label = $field_definition->getLabel() . " ";
      }
      $messages[$field_name] = strip_tags($violation->getMessage()
        ?->__toString());
    }
    return $messages;
  }

}
