<?php

namespace Drupal\content_lock\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\node\Entity\NodeType;
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
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManager $entityTypeManager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
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
      '#title' => $this->t('Enable this option to display messages to editor when he locks a content by editing it.'),
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

    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $options = [];
    foreach ($node_types as $node_type) {
      $options[$node_type->id()] = $node_type->label();
    }
    $form['entities']['node'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Node'),
      '#description' => $this->t('Select the bundles on which enable content lock'),
      '#options' => $options,
      '#default_value' => $config->get('node'),
    ];

    $block_content_types = $this->entityTypeManager->getStorage('block_content_type')->loadMultiple();
    $options = [];
    foreach ($block_content_types as $block_content_type) {
      $options[$block_content_type->id()] = $block_content_type->label();
    }
    $form['entities']['block_content'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Block content'),
      '#description' => $this->t('Select the Block content types on which enable content lock'),
      '#options' => $options,
      '#default_value' => $config->get('block_content'),
    ];

    $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();
    $options = [];
    foreach ($vocabularies as $vocabulary) {
      $options[$vocabulary->id()] = $vocabulary->label();
    }
    $form['entities']['taxonomy_term'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Vocabularies'),
      '#description' => $this->t('Select the vocabularies on which enable content lock'),
      '#options' => $options,
      '#default_value' => $config->get('taxonomy_term'),
    ];



    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('content_lock.settings')
      ->set('verbose', $form_state->getValue('verbose'))
      ->set('node', $this->removeEmptyValue($form_state->getValue('node')))
      ->set('block_content', $this->removeEmptyValue($form_state->getValue('block_content')))
      ->set('taxonomy_term', $this->removeEmptyValue($form_state->getValue('taxonomy_term')))
      ->save();
  }

  /**
   * Helper function to filter empty value in an array.
   *
   * @param array $array
   *   The array to check for empty values.
   *
   * @return array $array
   *   The array without empty values.
   */
  protected function removeEmptyValue($array) {
    return array_filter($array, function($value) {
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
  public static function formProcessMergeParent($element) {
    $parents = $element['#parents'];
    array_pop($parents);
    $element['#parents'] = $parents;
    return $element;
  }

}
