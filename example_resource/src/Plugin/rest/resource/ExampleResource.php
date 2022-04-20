<?php
/**
 * @file
 * Contains Drupal\example_resource\Plugin\rest\resource\ExampleResource
 */
  namespace Drupal\example_resource\Plugin\rest\resource;

  use Drupal\Core\Session\AccountProxyInterface;
  use Drupal\rest\Plugin\ResourceBase;
  use Drupal\rest\ResourceResponse;
  use Symfony\Component\DependencyInjection\ContainerInterface;
  use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
  use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
  use Symfony\Component\HttpKernel\Exception\HttpException;
  use Symfony\Component\HttpFoundation\Request;
  use Psr\Log\LoggerInterface;
  use Drupal\node\Entity\Node;
  use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

  /**
   * @RestResource(
   *   id = "example_resource",
   *   label = @Translation("Example Resource"),
   *   uri_paths = {
   *     "canonical" = "/example-resource/node/{content_type}",
   *   }
   * )
   */

  class ExampleResource extends ResourceBase {
    protected $currentRequest;
    protected $currentUser;

    /**
     * Constructs a Drupal\rest\Plugin\ResourceBase object.
     *
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin_id for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param array $serializer_formats
     *   The available serialization formats.
     * @param \Psr\Log\LoggerInterface $logger
     *   A logger instance.
     * @param \Drupal\Core\Session\AccountProxyInterface $current_user
     *   The current user instance.
     * @param Symfony\Component\HttpFoundation\Request $current_request
     *   The current request
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountProxyInterface $current_user, Request $current_request) {
      parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
      $this->currentUser = $current_user;
      $this->currentRequest = $current_request;
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
      return new static(
        $configuration,
        $plugin_id,
        $plugin_definition,
        $container->getParameter('serializer.formats'),
        $container->get('logger.factory')->get('rest'),
        $container->get('current_user'),
        $container->get('request_stack')->getCurrentRequest()
      );
    }

    /**
     * Responds to GET request
     *
     * @return ResourceResponse
     * the HTTP response object
     */

    public function get($content_type = NULL): ResourceResponse
    {
//      $param = $this->currentRequest->query->get('values');
//
//      if($param == 'all') {
//        throw new UnprocessableEntityHttpException('Gets all resources');
//      }

      # Authentication check
      if(!\Drupal::currentUser()->hasPermission('access content')) {
        throw new AccessDeniedHttpException();
      }

      # Param check
      if($content_type == NULL) {
        # /example-resource/node/?_format=json => No route found
        throw new UnprocessableEntityHttpException('Missing param');
      }

      $node = \Drupal::entityTypeManager()->getStorage('node');
      $query = $node->getQuery();

      /**
       * Gets all Node with type = $content_type
       */

      $ids = $query->condition('type', $content_type)->execute();

      if(!$ids){
        throw new UnprocessableEntityHttpException('No results');
      }
      $nodes = $node->loadMultiple($ids);
      $data = [];

      # Only get id and title field
      foreach ($nodes as $key => $value) {
        $data[] = ['id' => $value->id(), 'title' => $value->getTitle()];
      }
      $response = new ResourceResponse([$data, $content_type]);
      $response->addCacheableDependency([$data, $content_type]);
      return $response;
    }

  }


