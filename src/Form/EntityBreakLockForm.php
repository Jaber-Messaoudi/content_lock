<?php

namespace Drupal\content_lock\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Provides a base class for break content lock forms.
 */
class EntityBreakLockForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_type = $form_state->getValue('entity_type_id');
    $entity_id = $form_state->getValue('entity_id');
    /** @var \Drupal\content_lock\ContentLock\ContentLock $lock_service */
    $lock_service = \Drupal::service('content_lock');
    $lock_service->release($entity_id, NULL, $entity_type);
    drupal_set_message($this->t('Lock broken. Anyone can now edit this content.'));

    // Redirect URL to the request destination or the canonical entity view.
    if ($destination = \Drupal::request()->query->get('destination')) {
      $url = Url::fromUserInput($destination);
      $form_state->setRedirectUrl($url);
    }
    else {
      $this->redirect("entity.$entity_type.canonical", [$entity_type => $entity_id])->send();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'break_lock_entity';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ContentEntityInterface $entity = NULL) {
    $form['#title'] = t('Break Lock for content @label', ['@label' => $entity->label()]);
    $form['entity_id'] = [
      '#type' => 'value',
      '#value' => $entity->id(),
    ];
    $form['entity_type_id'] = [
      '#type' => 'value',
      '#value' => $entity->getEntityTypeId(),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Confirm break lock'),
    ];
    return $form;
  }

  /**
   * Custom access checker for the form route requirements.
   */
  public function access(ContentEntityInterface $entity, AccountInterface $account) {
    /** @var \Drupal\content_lock\ContentLock\ContentLock $lock_service */
    $lock_service = \Drupal::service('content_lock');
    return AccessResult::allowedIf($account->hasPermission('break content lock') || $lock_service->isLockedBy($entity->id(), $account->id(), $entity->getEntityTypeId()));
  }

}
