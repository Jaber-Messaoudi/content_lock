<?php

namespace Drupal\content_lock\Form;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Class ContentLockSettingsForm.
 *
 * @package Drupal\content_lock\Form
 */
class ContentLockSettingsForm extends ConfigFormBase {

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Extension\ModuleHandlerInterface module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManager $entityTypeManager, ModuleHandlerInterface $module_handler) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entityTypeManager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'content_lock.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'content_lock_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('content_lock.settings');

    $form['general'] = [
      '#type' => 'details',
      '#title' => $this->t('Verbose'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#process' => [[get_class($this), 'formProcessMergeParent']],
      '#weight' => 0,
    ];
    $form['general']['verbose'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable this option to display a message to the user when they lock a content item by editing it.'),
      '#description' => $this->t('Users trying to edit a content locked still see the content lock message.'),
      '#default_value' => $config->get('verbose'),
      '#return_value' => 1,
      '#empty' => 0,
    ];

    $form['entities'] = [
      '#type' => 'details',
      '#title' => $this->t('Entity type protected'),
      '#open' => TRUE,
      '#tree' => TRUE,
      '#process' => [[get_class($this), 'formProcessMergeParent']],
      '#weight' => 1,
    ];

    $definitions = $this->entityTypeManager->getDefinitions();
    foreach ($definitions as $definition) {
      if ($definition instanceof ContentEntityTypeInterface && $definition->getBundleEntityType()) {
        $bundles = $this->entityTypeManager
          ->getStorage($definition->getBundleEntityType())
          ->loadMultiple();

        $options = [];
        foreach ($bundles as $bundle) {
          $options[$bundle->id()] = $bundle->label();
        }
        if ($options) {
          $form['entities'][$definition->id()]['bundles'] = [
            '#type' => 'checkboxes',
            '#title' => $definition->getLabel(),
            '#description' => $this->t('Select the bundles on which enable content lock'),
            '#options' => $options,
            '#default_value' => $config->get('types.' . $definition->id()) ?: [],
          ];
        }
        $form['entities'][$definition->id()]['translation_lock'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Lock only on entity translation level.'),
          '#default_value' => in_array($definition->id(), $config->get('types_translation_lock')),
          '#description' => $this->t('Activating this options allows users to edit multiple translations concurrently'),
        ];
        if (!$this->moduleHandler->moduleExists('conflict')) {
          $form['entities'][$definition->id()]['translation_lock'] = [
            '#disabled' => TRUE,
            '#default_value' => FALSE,
            '#description' => $this->t('To allow editing multiple translations concurrently you need to install %module',
              [
                '%module' => $this->getLinkGenerator()->generate('Conflict', Url::fromUri('https://www.drupal.org/project/conflict')),
              ]
            ),
          ] + $form['entities'][$definition->id()]['translation_lock'];
        }
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $definitions = $this->entityTypeManager->getDefinitions();
    foreach ($definitions as $definition) {
      if ($definition instanceof ContentEntityTypeInterface && $definition->getBundleEntityType()) {
        if ($form_state->getValue($definition->id())) {
          $content_lock = $this->config('content_lock.settings');
          $content_lock->set('types.' . $definition->id(), $this->removeEmptyValue($form_state->getValue([$definition->id(), 'bundles'])));
          $translation_lock = (bool) $form_state->getValue([$definition->id(), 'translation_lock']);
          $types_translation_lock = $content_lock->get('types_translation_lock');
          if ($translation_lock && !in_array($definition->id(), $types_translation_lock)) {
            $types_translation_lock[] = $definition->id();
          }
          elseif (!$translation_lock && in_array($definition->id(), $types_translation_lock)) {
            $types_translation_lock = array_diff($types_translation_lock, [$definition->id()]);
          }
          $content_lock->set('types_translation_lock', $types_translation_lock);
        }
      }
    }

    $this->config('content_lock.settings')
      ->set('verbose', $form_state->getValue('verbose'))
      ->save();
  }

  /**
   * Helper function to filter empty value in an array.
   *
   * @param array $array
   *   The array to check for empty values.
   *
   * @return array
   *   The array without empty values.
   */
  protected function removeEmptyValue(array $array) {
    return array_filter($array, function ($value) {
      return !empty($value);
    });
  }

  /**
   * Merge elements to the level up.
   *
   * Render API callback: Moves entity_reference specific Form API elements
   * (i.e. 'handler_settings') up a level for easier processing values.
   *
   * @param array $element
   *   The array to filter.
   *
   * @return array
   *   The array filtered.
   *
   * @see _entity_reference_field_settings_process()
   */
  public static function formProcessMergeParent(array $element) {
    $parents = $element['#parents'];
    array_pop($parents);
    $element['#parents'] = $parents;
    return $element;
  }

}
