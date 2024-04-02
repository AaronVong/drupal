<?php

namespace Drupal\custom_oauth2\Repositories;

use Drupal\simple_oauth\Entities\UserEntity;
use Drupal\user\UserAuthInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;

class Co2UserRepositories implements UserRepositoryInterface {

  /**
   * @var \Drupal\user\UserAuthInterface
   */
  protected $userAuth;

  /**
   * UserRepository constructor.
   *
   * @param \Drupal\user\UserAuthInterface $user_auth
   *   The service to check the user authentication.
   */
  public function __construct(UserAuthInterface $user_auth) {
    $this->userAuth = $user_auth;
  }
  public function getUserEntityByUserCredentials($username, $password, $grantType, ClientEntityInterface $clientEntity) {
    if ($uid = $this->userAuth->authenticate($username, $password)) {
      $user = new UserEntity();
      $user->setIdentifier($uid);

      return $user;
    }
    return NULL;
  }

}
