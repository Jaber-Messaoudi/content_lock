<?php

namespace Drupal\content_lock\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Class BreakLockTermForm.
 *
 * @package Drupal\content_lock\Form
 */
class BreakLockTermForm extends BreakLockFormBase {

  protected function getEntityType() {
    return 'taxonomy_term';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'break_lock_term';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Term $taxonomy_term = NULL) {
    $form['#title'] = t('Break Lock for Taxonomy Term @label', ['@label' => $taxonomy_term->label()]);
    $form['entity_id'] = [
      '#type' => 'hidden',
      '#value' => $taxonomy_term->id(),
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
  public function access(Term $taxonomy_term, AccountInterface $account) {
    return $this->accessChecker('taxonomy_term', $taxonomy_term->id(), $account);
  }

}
