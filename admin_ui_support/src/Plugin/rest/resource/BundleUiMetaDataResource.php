<?php

namespace Drupal\admin_ui_support\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a rest resource for the menu tree.
 *
 * @RestResource(
 *   id = "bundle_ui",
 *   label = @Translation("Bundle UI MetaData"),
 *   uri_paths = {
 *     "canonical" = "/admin-api/ui-metadata/{entity_type_id}/{bundle_id}/{mode}"
 *   }
 * )
 */
class BundleUiMetaDataResource extends ResourceBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a MenuTreeResource object.
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
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menuLinkTree
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entityTypeManager;
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
      $container->get('entity_type.manager')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the list of available permissions.
   */
  public function get($entity_type_id, $bundle_name, $mode) {
    $dependencies = [];
    $form_display = $this->entityTypeManager->getStorage('entity_form_display')->load($entity_type_id . '.' . $bundle_name . '.' . $mode);
    $dependencies[] = $form_display;
    $components = $form_display->getComponents();
    $field_config_storage = $this->entityTypeManager->getStorage('field_config');
    $field_config_ids = $field_config_storage->getQuery()
      ->condition('field_name', array_keys($components))
      ->condition('entity_type', $entity_type_id)
      ->condition('bundle', $bundle_name)
      ->execute();
    /** @var \Drupal\field\Entity\FieldConfig[] $field_configs */
    $field_configs = $field_config_storage->loadMultiple($field_config_ids);
    $field_storage_config_storage = $this->entityTypeManager->getStorage('field_storage_config');
    $field_storage_ids = $field_storage_config_storage->getQuery()
      ->condition('entity_type', $entity_type_id)
      ->condition('field_name', array_keys($components))
      ->execute();
    $field_storage_configs = $field_storage_config_storage->loadMultiple($field_storage_ids);

    $entity_type =  $this->entityTypeManager->getDefinition($entity_type_id);
    $entity_type->
    foreach ($components as $component_id => &$component) {
      $field_config_id = "$entity_type_id.$bundle_name.$component_id";
      if (isset($field_configs[$field_config_id])) {
        $field_config = $field_configs[$field_config_id];
        $dependencies[] = $field_config;
        $component['label'] = $field_config->label();
        $component['config_settings'] = $field_config->getSettings();


        $field_storage_id = "$entity_type_id.$component_id";
        if (isset($field_storage_configs[$field_storage_id])) {
          $field_storage_config = $field_storage_configs[$field_storage_id];
          $dependencies[] = $field_storage_config;
          $component['storage_settings'] = $field_storage_config->getSettings();
          $component['storage_type'] = $field_storage_config->getType();
        }
      }



    }
    $response = new ResourceResponse($components);
    foreach ($dependencies as $dependency) {
      $response->addCacheableDependency($dependency);
    }
    return $response;
  }



}
