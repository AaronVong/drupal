<?php

namespace Drupal\custom_oauth2\Grant;

use DateInterval;
use Drupal\Core\Session\AccountInterface;
use Drupal\simple_oauth\Entities\UserEntity;
use Drupal\user\Entity\User;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Co2 Password grant type class
 */
class Co2PasswordGrant extends PasswordGrant {

  /**
   * @inheritdoc
   */
  public function validateUser(ServerRequestInterface $request, ClientEntityInterface $client) {
    $oauth_user = parent::validateUser($request, $client);
    $account_validator = \Drupal::service('custom_oauth2.account_validator');
    $user = User::load($oauth_user->getIdentifier());


    $is_account_verified = $account_validator->isAccountOtpVerified($user);
    if (!$is_account_verified) {
      $message = "Please verify your account with the OTP sent to your sign up email.";
      $error_type = "account_verification_required";
      $hint = "Your uid is @" . $user->id();

      throw new OAuthServerException($message, 0, $error_type, 400, $hint);
    }

    if ($user->isBlocked()) {
      $error_type = "account_blocked";
      $hint = "Contact administrator to support.";
      $message = "Your account is blocked, please contact administrator for more information.";
      throw new OAuthServerException($message, 0, $error_type, 400, $hint);
    }
    return $oauth_user;
  }

  /**
   * @inheritdoc
   */
  public function getIdentifier() {
    return "co2_password";
  }

  /**
   * Grant token for a user account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account.
   * @param string $client_id
   *   Client id.
   * @param string $client_secret
   *   Client secret.
   * @param \League\OAuth2\Server\ResponseTypes\BearerTokenResponse $response_type
   *   Response type.
   *
   * @return \League\OAuth2\Server\ResponseTypes\BearerTokenResponse
   *   Response return.
   *
   * @throws \League\OAuth2\Server\Exception\OAuthServerException
   *   Throws.
   * @throws \League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException
   *   Throws.
   */
  public function grantAccessTokenForAccount(AccountInterface $account, $client_id, $client_secret, BearerTokenResponse $response_type) {
    $client = $this->validateClientCredentials($client_id, $client_secret);
    $scopes = $this->validateScopes($this->defaultScope);

    $user = new UserEntity();
    $user->setIdentifier($account->id());

    $finalizedScopes = $this->scopeRepository->finalizeScopes($scopes, $this->getIdentifier(), $client, $user->getIdentifier());
    $accessTokenTTL = \DateInterval::createFromDateString('+120 days');
    $accessToken = $this->issueAccessToken($accessTokenTTL, $client, $user->getIdentifier(), $finalizedScopes);

    $response_type->setAccessToken($accessToken);
    $refreshToken = $this->issueRefreshToken($accessToken);
    if ($refreshToken !== NULL) {
      $response_type->setRefreshToken($refreshToken);
    }

    return $response_type;
  }

  /**
   * Validate client.
   *
   * @param string $client_id
   *   Client id.
   * @param string $client_secret
   *   Client secret.
   *
   * @return \League\OAuth2\Server\Entities\ClientEntityInterface
   *   Client.
   */
  public function validateClientCredentials($client_id, $client_secret) {
    $client = $this->clientRepository->getClientEntity($client_id);
    if ($client instanceof ClientEntityInterface === FALSE) {
      $server_request = \Drupal::service('psr7.http_message_factory')
        ->createRequest(\Drupal::request());
      throw OAuthServerException::invalidClient($server_request);
    }
    return $client;
  }

}
