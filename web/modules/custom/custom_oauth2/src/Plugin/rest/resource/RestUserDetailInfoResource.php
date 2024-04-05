<?php

declare(strict_types=1);

namespace Drupal\custom_oauth2\Plugin\rest\resource;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\jsonapi\Serializer\Serializer;

/**
 * Represents Rest User Detail Info Resource records as resources.
 *
 * @RestResource (
 *   id = "rest_user_detail_info_resource",
 *   label = @Translation("Rest User Detail Info Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/user-info"
 *   }
 * )
 *
 * @DCG
 * The plugin exposes key-value records as REST resources. In order to enable it
 * import the resource configuration into active configuration storage. An
 * example of such configuration can be located in the following file:
 * core/modules/rest/config/optional/rest.resource.entity.node.yml.
 * Alternatively, you can enable it through admin interface provider by REST UI
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
final class RestUserDetailInfoResource extends ResourceBase {

  protected AccountInterface $currentUser;

  /**
   * The key-value storage.
   */
  private readonly KeyValueStoreInterface $storage;

  private array $allowed_fields = [
    "uid",
    "name",
    'mail',
    'field_u_first_name',
    'field_u_last_name',
    'user_picture',
    'status'
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    KeyValueFactoryInterface $keyValueFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->storage = $keyValueFactory->get('rest_user_detail_info_resource');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance =  new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('keyvalue')
    );
    $instance->currentUser = $container->get('current_user');
    return $instance;
  }

  /**
   * Responds to GET requests.
   */
  public function get(Request $request): ModifiedResourceResponse {
    $uid = $this->currentUser->id();
    $user = User::load($uid);
    $data = [];
    foreach ($user->getFields() as $key => $field) {
      if (in_array($key, $this->allowed_fields)) {
        $data[$key] = $field->getString();
      }
    }

    return new ModifiedResourceResponse(['message' => 'Success', "data" => $data], 200);
  }

}
