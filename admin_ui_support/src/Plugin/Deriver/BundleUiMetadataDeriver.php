<?php

namespace Drupal\admin_ui_support\Plugin\Deriver;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives REST resources for all simple config objects.
 */
class BundleUiMetadataDeriver implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * List of derivative definitions.
   *
   * @var array
   */
  protected $derivatives;

  protected $entityTypeManager;

  /**
   * BundleUiMetadataDeriver constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinition($derivative_id, $base_plugin_definition) {
    if (!isset($this->derivatives)) {
      $this->getDerivativeDefinitions($base_plugin_definition);
    }
    if (isset($this->derivatives[$derivative_id])) {
      return $this->derivatives[$derivative_id];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if (isset($this->derivatives)) {
      return $this->derivatives;
    }

    foreach ($this->entityTypeManager->getDefinitions() as $definition) {
      $class = $definition->getClass();
      if (in_array(FieldableEntityInterface::class, class_implements($class))) {
        if ($bundle_entity_type_id = $definition->getBundleEntityType()) {
          $bundle_storage = $this->entityTypeManager->getStorage($bundle_entity_type_id);

          foreach ($bundle_storage->loadMultiple() as $bundle) {
            $entity_form_storage = $this->entityTypeManager->getStorage('entity_form_display');
            /* @var \Drupal\Core\Entity\Entity\EntityFormDisplay{} $form_displays */
            $form_displays = $entity_form_storage->loadByProperties(['targetEntityType' => $definition->id(), 'bundle' => $bundle->id()]);
            foreach ($form_displays as $form_display) {
              $form_display_id = $form_display->id();
              $mode = array_pop(explode('.', $form_display_id));
              $derivative_id = $definition->id() . '__' . $bundle->id() . '__' . $mode;
              $this->derivatives[$derivative_id] = [
                'id' => "bundle_ui:$derivative_id",
                'entity_type_id' => $definition->id(),
                'bundle_name' => $bundle->id(),
                'form_mode' => $mode,
                'label' => $this->t(
                  '@entity @bundle @mode UI Metadata',
                  [
                    '@entity' => $definition->getLabel(),
                    '@bundle' => $bundle->label(),
                    '@mode' => $form_display->label(),
                  ]),
                'uri_paths' => [
                  'canonical' => "/admin-api/ui-metadata/{$definition->id()}/{$bundle->id()}/{$form_display->id()}",
                ],
              ] + $base_plugin_definition;
            }
          }
        }
      }
    }

    return $this->derivatives;
  }

}
