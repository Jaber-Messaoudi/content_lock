<?php

namespace Drupal\content_lock\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

/**
 * Provides a base class for break content lock forms.
 */
abstract class BreakLockFormBase extends FormBase  {

  protected function getEntityType() {
    return 'node';
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_type = $this->getEntityType();
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
      $this->redirect("entity.$entity_type.canonical", array($entity_type => $entity_id))->send();
    }
  }

  /**
   * Common access checker for all entity types.
   */
  protected function accessChecker($entity_type, $entity_id, AccountInterface $account) {
    /** @var \Drupal\content_lock\ContentLock\ContentLock $lock_service */
    $lock_service = \Drupal::service('content_lock');
    return AccessResult::allowedIf($account->hasPermission('break content lock') || $lock_service->isLockedBy($entity_id, $account->id(), $entity_type));
  }
}
