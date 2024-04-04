<?php

namespace Drupal\custom_oauth2\Plugin\rest\resource;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\custom_oauth2\AccountOtpTrait;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Represents Rest Sign Up records as resources.
 *
 * @RestResource (
 *   id = "custom_oauth2_rest_sign_up",
 *   label = @Translation("Rest Sign Up"),
 *   uri_paths = {
 *     "create" = "/api/user/sign-up"
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
class RestSignUpResource extends ResourceBase {

  use AccountOtpTrait;
  /**
   * The key-value storage.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $storage;

  /**
   * @var \Drupal\custom_oauth2\Services\AccountValidator
   */
  protected $account_validator;

  /**
   * @var \Drupal\custom_oauth2\Services\Co2Ultilities
   */
  protected $co2Ultilities;

  /**
   * @var \Drupal\custom_oauth2\Services\AccountVerify
   */
  protected $account_verify;

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
    $this->storage = $keyValueFactory->get('custom_oauth2_rest_sign_up');
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
    $instance->account_validator = $container->get('custom_oauth2.account_validator');
    $instance->co2Ultilities = $container->get('custom_oauth2.co2ultilities');
    $instance->account_verify = $container->get('custom_oauth2.account_verity');
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
  public function post(array $data) {
    if (empty($data)) {
      return new ModifiedResourceResponse(['message' => "Empty data, failed to sign up"], 400);
    }
    try {
      $new_account = User::create();
      $new_account->setUsername($data['name']);
      $new_account->setPassword($data['pass']);
      $new_account->setEmail($data['mail']);
      $new_account->set('field_u_first_name', $data['field_u_first_name']);
      $new_account->set('field_u_last_name', $data['field_u_last_name']);
      $custom_oauth2_settings = \Drupal::config('custom_oauth2.settings');
      $roles = $custom_oauth2_settings->get('roles_assign');
      // Assign roles that been config in form
      if (!empty($roles)) {
        foreach ($roles as $key => $value) {
          if (!empty($value)) {
            $new_account->addRole($key);
          }
        }
      }
      $violation_list = $new_account->validate();
      $errors = $this->co2Ultilities->getViolationMessages($violation_list);
      if (!empty($errors)) {
        return new ModifiedResourceResponse(['message' => $errors], 400);
      }

      $errors = $this->account_validator->passwordValidator($data['pass'], $new_account);
      if ($errors->status === FALSE) {
        return new ModifiedResourceResponse(['message' => $errors->message], 400);
      }

      if (!$this->account_validator->confirmPasswordValidator($data['pass'], $data['pass_confirm'])) {
        return new ModifiedResourceResponse(['message' => ['pass_confirm' => "This value should be match"]], 400);
      }

      $new_account->block();
      $new_account->save();
      $is_sandbox_enabled = $custom_oauth2_settings->get('otp_sandbox');
      if (empty($is_sandbox_enabled)) {
        $this->account_verify->sendOtpToEmail($new_account);
      }
      return new ModifiedResourceResponse(['message' => 'Sign up successful, an email with OTP has been sent', 'uid' => $new_account->id(), 'data' => $this->getOtpData($new_account->id())], 200);
    } catch (\Exception $exception) {
      $this->logger->error($exception->getMessage());
      return new ModifiedResourceResponse(['message' => 'Failed to sign up'], 500);
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

}
