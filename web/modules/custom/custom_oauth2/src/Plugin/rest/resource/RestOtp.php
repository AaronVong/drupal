<?php

namespace Drupal\goodevening_account\Plugin\rest\resource;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Represents Rest OTP as resources.
 *
 * @RestResource (
 *   id = "goodevening_account_rest_otp",
 *   label = @Translation("Rest OTP"),
 *   uri_paths = {
 *     "create" = "/api/otp/{action}"
 *   }
 * )
 *
 * @DCG
 * The plugin exposes key-value records as REST resources. In order to enable it
 * import the resource configuration into active configuration storage. An
 * example of such configuration can be located in the following file:
 * core/modules/rest/config/optional/rest.resource.entity.node.yml.
 * Alternatively you can enable it through admin interface provider by REST UI
 * module.
 * @see https://www.drupal.org/project/restui
 *
 * @DCG
 * Notice that this plugin does not provide any validation for the data.
 * Consider creating custom normalizer to validate and normalize the incoming
 * data. It can be enabled in the plugin definition as follows.
 * @code
 *   serialization_class = "Drupal\foo\MyDataStructure",
 * @endcode
 *
 * @DCG
 * For entities, it is recommended to use REST resource plugin provided by
 * Drupal core.
 * @see \Drupal\rest\Plugin\rest\resource\EntityResource
 */
class RestOtp extends ResourceBase {

  /**
   * The key-value storage.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $storage;

  /**
   * @var \Drupal\goodevening_account\Services\AccountValidator
   */
  protected $account_validator;

  /**
   * @var \Drupal\goodevening_helper\Services\GEHelper
   */
  protected $gehelper;

  /**
   * @var \Drupal\goodevening_account\Services\AccountVerify
   */
  protected $account_verify;

  /**
   * @var \Drupal\goodevening_account\Services\GrantToken
   */
  protected $grant_token;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array                    $configuration,
                             $plugin_id,
                             $plugin_definition,
    array                    $serializer_formats,
    LoggerInterface          $logger,
    KeyValueFactoryInterface $keyValueFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger, $keyValueFactory);
    $this->storage = $keyValueFactory->get('goodevening_account_rest_sign_up');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('keyvalue')
    );
    $instance->account_validator = $container->get('goodevening_account.account_validator');
    $instance->gehelper = $container->get('goodevening_helper.gehelper');
    $instance->account_verify = $container->get('goodevening_account.account_verity');
    $instance->grant_token = $container->get('goodevening_account.grant_token');
    return $instance;
  }

  /**
   * Responds to POST requests and saves the new record.
   *
   * @param array $data
   *   Data to write into the database.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   */
  public function post(array $data, $action) {
    if (empty($action)) {
      return new ModifiedResourceResponse(['message' => "Empty action, failed to process OTP"], 400);
    }

    try {
      $user = User::load($data['uid']);
      if (empty($user)) {
        return new ModifiedResourceResponse(['message' => "User does not exists."], 400);
      }

      if ($action == "verify") {
        return $this->verifyOtp($user, $data);
      }
      elseif ($action === "resend") {
        $is_success = $this->account_verify->reSendOtpToEmail($user);
        if (!$is_success) {
          return new ModifiedResourceResponse(['message' => "Failed to re-send OTP, account is already activate or reach re-send limitation, please contact administrator for support."], 400);
        }
      }
      else {
        throw new \Exception('Invalid action for rest otp');
      }
      return new ModifiedResourceResponse(['message' => 'Success, OTP has been ' . $action], 200);
    } catch (\Exception $exception) {
      $this->logger->error($exception->getMessage());
      return new ModifiedResourceResponse(['message' => 'Failed to ' . $action . ' OTP'], 500);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getBaseRoute($canonical_path, $method) {
    $route = parent::getBaseRoute($canonical_path, $method);
    // Set ID validation pattern.
    if ($method != 'POST') {
      $route->setRequirement('id', '\d+');
    }
    return $route;
  }

  /**
   * Returns next available ID.
   */
  private function getNextId() {
    $ids = \array_keys($this->storage->getAll());
    return count($ids) > 0 ? max($ids) + 1 : 1;
  }

  private function verifyOtp(User $user, array $data): ModifiedResourceResponse {
    if (empty($data)) {
      return new ModifiedResourceResponse(['message' => "Empty data, failed to process OTP"], 400);
    }

    if (empty($data['client_id']) || empty($data['client_secret'])) {
      return new ModifiedResourceResponse(['message' => "Invalid client, failed to verify OTP"], 400);
    }
    $result = $this->account_verify->verifyOTP($user, $data['otp']);
    if (empty($result['status'])) {
      return new ModifiedResourceResponse(['message' => $result['message']], 400);
    }
    $this->account_verify->activateUser($user);
    $client_id = $data['client_id'];
    $client_secret = $data['client_secret'];

    try {
      $token = $this->grant_token->grantPasswordAccount($user, $client_id, $client_secret)->getBody();
      return new ModifiedResourceResponse(json_decode($token, TRUE), 200);
    }
    catch(\Exception $exception) {
      $this->logger->error($exception->getMessage());
      return new ModifiedResourceResponse(['message' => 'Account verified but failed to login'], 401);
    }
  }

}
