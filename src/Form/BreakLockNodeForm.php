<?php

namespace Drupal\content_lock\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;

/**
 * Class BreakLockNodeForm.
 *
 * @package Drupal\content_lock\Form
 */
class BreakLockNodeForm extends BreakLockFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'break_lock_node';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Node $node = NULL) {
    $form['#title'] = t('Break Lock for content @label', ['@label' => $node->label()]);
    $form['entity_id'] = [
      '#type' => 'hidden',
      '#value' => $node->id(),
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
  public function access(Node $node, AccountInterface $account) {
    return $this->accessChecker('node', $node->id(), $account);
  }

}
