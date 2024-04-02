<?php

namespace Drupal\goodevening_account\Services;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\password_policy\PasswordPolicyValidator;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

class AccountValidator {

  /**
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $current_user;

  /**
   * @var PasswordPolicyValidator
   */
  protected $password_validator;

  public function __construct(AccountProxyInterface $current_user, PasswordPolicyValidator $password_validator) {
    $this->current_user = $current_user;
    $this->password_validator = $password_validator;
  }

  public function passwordValidator(string|null $password, UserInterface $user): object {
    $result = new \stdClass();
    $result->status = TRUE;
    $result->message = [];

    if (empty($password)) {
      $result->status = FALSE;
      $result->message['pass'] = "This value should not be null";
      return $result;
    }
    $validation_result = $this->password_validator->validatePassword($password, $user, ['authenticated']);
    if ($validation_result->isInvalid()) {
      $result->status = FALSE;
      $result->message['pass'] = $validation_result->getErrors();
    }
    return $result;
  }

  public function confirmPasswordValidator(string|null $password, string|null $password_confirm) {
    if (empty($password) || empty($password_confirm)) {
      return FALSE;
    }

    if (strcmp($password, $password_confirm) !== 0) {
      return FALSE;
    }

    return TRUE;
  }

  public function isAccountOtpVerified(UserInterface $user) {
    if(empty($user)) {
      return FALSE;
    }
    $otp_verfied = $user->get('field_u_otp_verified')->getString();
    if (empty($otp_verfied)) {
      return FALSE;
    }

    return TRUE;
  }
}
