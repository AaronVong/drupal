<?php

namespace Drupal\custom_oauth2\Services;

use Defuse\Crypto\Core;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Site\Settings;
use Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface;
use Drupal\user\Entity\User;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;

/**
 * GrantToken Class to generate oauth2 token
 */
class GrantToken {

  protected $grantManager;

  protected $entityTypeManager;

  protected $bearerTokenResponse;

  protected $configFactory;

  protected $privateKey;

  /**
   * GrantToken constructor.
   *
   * @param \Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface $grantManager
   *   Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Drupal\Core\Entity\EntityTypeManagerInterface.
   * @param \League\OAuth2\Server\ResponseTypes\BearerTokenResponse $bearerTokenResponse
   *   League\OAuth2\Server\ResponseTypes\BearerTokenResponse.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Drupal\Core\Config\ConfigFactoryInterface.
   */
  public function __construct(
    Oauth2GrantManagerInterface $grantManager,
    EntityTypeManagerInterface  $entityTypeManager,
    BearerTokenResponse         $bearerTokenResponse,
    ConfigFactoryInterface      $configFactory
  ) {
    $this->grantManager = $grantManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->bearerTokenResponse = $bearerTokenResponse;
    $this->configFactory = $configFactory;
    $private_key = $this->configFactory->get('simple_oauth.settings')
      ->get('private_key');
    $this->privateKey = realpath($private_key);
  }

  /**
   * Grant token type password for account.
   *
   * @param \Drupal\user\Entity\User $account
   *   Account to grant.
   * @param string $client_id
   *   Client id.
   * @param string $client_secret
   *   Client secret.
   *
   * @return \League\OAuth2\Server\ResponseTypes\BearerTokenResponse|\Psr\Http\Message\ResponseInterface
   *   Response.
   *
   * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \League\OAuth2\Server\Exception\OAuthServerException
   * @throws \League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException
   */
  public function grantPasswordAccount(User $account, $client_id, $client_secret) {
    $plugin = $this->grantManager->createInstance('ge_password');
    /** @var \Drupal\custom_oauth2\Grant\Co2PasswordGrant $grant */
    $grant = $plugin->getGrantType();
    $consumer_storage = $this->entityTypeManager->getStorage('consumer');
    $client_drupal_entities = $consumer_storage
      ->loadByProperties([
        'uuid' => $client_id,
      ]);
    if (empty($client_drupal_entities)) {
      throw OAuthServerException::serverError('Client could not be found.');
    }
    $client_drupal_entity = reset($client_drupal_entities);
    $auth_server = $this->grantManager->getAuthorizationServer('ge_password', $client_drupal_entity);
    $auth_server->enableGrantType($grant);

    $bearer_response = $grant->grantAccessTokenForAccount($account, $client_id, $client_secret, $this->bearerTokenResponse);

    $bearer_response->setPrivateKey(new CryptKey($this->privateKey));

    $salt = Settings::getHashSalt();
    // The hash salt must be at least 32 characters long.
    if (Core::ourStrlen($salt) < 32) {
      throw OAuthServerException::serverError('Hash salt must be at least 32 characters long.');
    }
    $bearer_response->setEncryptionKey(Core::ourSubstr($salt, 0, 32));

    return $bearer_response->generateHttpResponse(new Response());
  }

}
