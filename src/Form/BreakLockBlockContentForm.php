<?php

namespace Drupal\content_lock\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\block_content\Entity\BlockContent;

/**
 * Class BreakLockBlockContentForm.
 *
 * @package Drupal\content_lock\Form
 */
class BreakLockBlockContentForm extends BreakLockFormBase {

  protected function getEntityType() {
    return 'block_content';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'break_lock_block_content';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, BlockContent $block_content = NULL) {
    $form['#title'] = t('Break Lock for Block @label', ['@label' => $block_content->label()]);
    $form['entity_id'] = [
      '#type' => 'hidden',
      '#value' => $block_content->id(),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Confirm break lock'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
  }

  /**
   * Custom access checker for the form route requirements.
   */
  public function access(BlockContent $block_content, AccountInterface $account) {
    return $this->accessChecker('block_content', $block_content->id(), $account);
  }

}
