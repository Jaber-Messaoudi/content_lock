<?php

namespace Drupal\content_lock\ContentLock;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Link;
use Drupal\Core\Datetime\DateFormatter;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class ContentLock.
 *
 * The content lock service.
 */
class ContentLock extends ServiceProviderBase {

  use StringTranslationTrait;

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   *   The database service.
   */
  protected $database;

  /**
   * The module_handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   *   The module_handler service.
   */
  protected $moduleHandler;

  /**
   * The csrf_token service.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   *   The csrf_token service.
   */
  protected $csrfToken;

  /**
   * The date.formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   *   The date.formatter service.
   */
  protected $dateFormatter;

  /**
   * The current_user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   *   The current_user service.
   */
  protected $currentUser;

  /**
   * The config.factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   *   The config.factory service.
   */
  protected $configFactory;

  /**
   * The redirect.destination service.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   *   The current request.
   */
  protected $currentRequest;

  /**
   * The entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity_type.manager service.
   */
  protected $entityTypeManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Extension\ModuleHandler $moduleHandler
   *   The module Handler service.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrfToken
   *   The csrfTokenGenerator service.
   * @param \Drupal\Core\Datetime\DateFormatter $dateFormatter
   *   The date.formatter service.
   * @param \Drupal\Core\Session\AccountProxy $currentUser
   *   The current_user service.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The config.factory service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity_type.manager service.
   */
  public function __construct(Connection $database, ModuleHandler $moduleHandler, CsrfTokenGenerator $csrfToken, DateFormatter $dateFormatter, AccountProxy $currentUser, ConfigFactory $configFactory, RequestStack $requestStack, EntityTypeManagerInterface $entityTypeManager) {
    $this->database = $database;
    $this->moduleHandler = $moduleHandler;
    $this->csrfToken = $csrfToken;
    $this->dateFormatter = $dateFormatter;
    $this->currentUser = $currentUser;
    $this->configFactory = $configFactory;
    $this->currentRequest = $requestStack->getCurrentRequest();
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Check if an internal Drupal path should be protected with a token.
   *
   * Adds requirements that certain path be accessed only through tokenized URIs
   * which are enforced by this module. This prevents people from being CSRFed
   * into locking nodes that they can access without meaning to lock them.
   *
   * @param string $path
   *   The path to check.
   *
   * @return bool
   *   Returns TRUE if the path is protected or FALSE if not protected.
   */
  public function isPathProtected($path) {
    $cache = &drupal_static(__FUNCTION__, []);

    // Check cache.
    if (isset($cache[$path])) {
      return $cache[$path];
    }

    // Invoke hook and collect grants/denies for protected paths.
    $protected = [];
    foreach ($this->moduleHandler->getImplementations('content_lock_path_protected') as $module) {
      $protected = array_merge(
        $protected,
        [
          $module => $this->moduleHandler->invoke($module, 'content_lock_path_protected', $path),
        ]
      );
    }

    // Allow other modules to alter the returned grants/denies.
    $this->moduleHandler->alter('content_lock_path_protected', $protected, $path);

    // If TRUE is returned, path is protected.
    $cache[$path] = in_array(TRUE, $protected);

    return $cache[$path];
  }

  /**
   * Calculate the token required to unlock a node.
   *
   * Tokens are required because they prevent CSRF.
   *
   * @see https://security.drupal.org/node/2429
   */
  public function getReleaseToken($nid) {
    // Get a drupal CSRF token. (The actual service is called 'csrf_token').
    return $this->csrfToken->get("content_lock/release/$nid");
  }

  /**
   * Fetch the lock for an entity.
   *
   * @param int $entity_id
   *   The entity id.
   * @param string $langcode
   *   The translation language code of the entity.
   * @param string $entity_type
   *   The entity type.
   *
   * @return object
   *   The lock for the node. FALSE, if the document is not locked.
   */
  public function fetchLock($entity_id, $langcode, $entity_type = 'node') {
    if (!$this->isTranslationLockEnabled($entity_type)) {
      $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    }
    $query = $this->database->select('content_lock', 'c');
    $query->leftJoin('users_field_data', 'u', '%alias.uid = c.uid');
    $query->fields('c')
      ->fields('u', ['name'])
      ->condition('c.entity_type', $entity_type)
      ->condition('c.entity_id', $entity_id)
      ->condition('c.langcode', $langcode);

    return $query->execute()->fetchObject();
  }

  /**
   * Tell who has locked node.
   *
   * @param object $lock
   *   The lock for a node.
   * @param bool $translation_lock
   *   Defines whether the lock is on translation level or not.
   *
   * @return string
   *   String with the message.
   */
  public function displayLockOwner($lock, $translation_lock) {
    $username = $this->entityTypeManager->getStorage('user')->load($lock->uid);
    $date = $this->dateFormatter->formatInterval(REQUEST_TIME - $lock->timestamp);

    if ($translation_lock) {
      $message = $this->t('This content translation is being edited by the user @name and is therefore locked to prevent other users changes. This lock is in place since @date.', [
        '@name' => $username->getDisplayName(),
        '@date' => $date,
      ]);
    }
    else {
      $message = $this->t('This content is being edited by the user @name and is therefore locked to prevent other users changes. This lock is in place since @date.', [
        '@name' => $username->getDisplayName(),
        '@date' => $date,
      ]);
    }
    return $message;
  }

  /**
   * Check lock status.
   *
   * @param int $entity_id
   *   The entity id.
   * @param string $langcode
   *   The translation language code of the entity.
   * @param int $uid
   *   The user id.
   * @param string $entity_type
   *   The entity type.
   *
   * @return bool
   *   Return TRUE OR FALSE.
   */
  public function isLockedBy($entity_id, $langcode, $uid, $entity_type = 'node') {
    if (!$this->isTranslationLockEnabled($entity_type)) {
      $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    }
    /** @var \Drupal\Core\Database\Query\SelectInterface $query */
    $query = $this->database->select('content_lock', 'c')
      ->fields('c')
      ->condition('entity_id', $entity_id)
      ->condition('uid', $uid)
      ->condition('entity_type', $entity_type)
      ->condition('langcode', $langcode);;
    $num_rows = $query->countQuery()->execute()->fetchField();
    return (bool) $num_rows;
  }

  /**
   * Release a locked entity.
   *
   * @param int $entity_id
   *   The entity id.
   * @param string $langcode
   *   The translation language code of the entity.
   * @param int $uid
   *   If set, verify that a lock belongs to this user prior to release.
   * @param string $entity_type
   *   The entity type.
   */
  public function release($entity_id, $langcode, $uid = NULL, $entity_type = 'node') {
    if (!$this->isTranslationLockEnabled($entity_type)) {
      $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    }
    // Delete locking item from database.
    $this->lockingDelete($entity_id, $langcode, $uid, $entity_type);

    $this->moduleHandler->invokeAll(
      'content_lock_release',
      [$entity_id, $langcode, $entity_type]
    );
  }

  /**
   * Release all locks set by a user.
   *
   * @param int $uid
   *   The user uid.
   */
  protected function releaseAllUserLocks($uid) {
    $this->database->delete('content_lock')
      ->condition('uid', $uid)
      ->execute();
  }

  /**
   * Save lock warning.
   *
   * @param string $message
   *   Message string.
   * @param int $entity_id
   *   The entity id.
   */
  protected function saveLockWarning($message, $entity_id) {
    if (empty($_SESSION['content_lock'])) {
      $_SESSION['content_lock'] = '';
    }
    $data = unserialize($_SESSION['content_lock']);
    if (!is_array($data)) {
      $data = [];
    }

    if (array_key_exists($entity_id, $data)) {
      return;
    }

    $data[$entity_id] = $message;
    $_SESSION['content_lock'] = serialize($data);
  }

  /**
   * Show warnings.
   */
  protected function showWarnings() {
    $user = $this->currentUser;
    if (empty($_SESSION['content_lock'])) {
      return;
    }
    $data = unserialize($_SESSION['content_lock']);
    if (!is_array($data) || count($data) == 0) {
      return;
    }
    foreach ($data as $entity_id => $messsage) {
      if ($this->isLocked($entity_id, $user->id())) {
        drupal_set_message($messsage, 'warning', FALSE);
      }
    }
    $_SESSION['content_lock'] = '';
  }

  /**
   * Save locking into database.
   *
   * @param int $entity_id
   *   The entity id.
   * @param string $langcode
   *   The translation language of the entity.
   * @param int $uid
   *   The user uid.
   * @param string $entity_type
   *   The entity type.
   *
   * @return bool
   *   The result of the merge query.
   */
  protected function lockingSave($entity_id, $langcode, $uid, $entity_type = 'node') {
    if (!$this->isTranslationLockEnabled($entity_type)) {
      $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    }
    $result = $this->database->merge('content_lock')
      ->key([
        'entity_id' => $entity_id,
        'entity_type' => $entity_type,
        'langcode' => $langcode,
      ])
      ->fields([
        'entity_id' => $entity_id,
        'entity_type' => $entity_type,
        'langcode' => $langcode,
        'uid' => $uid,
        'timestamp' => REQUEST_TIME,
      ])
      ->execute();

    return $result;
  }

  /**
   * Delete locking item from database.
   *
   * @param int $entity_id
   *   The entity id.
   * @param string $langcode
   *   The translation language of the entity.
   * @param int $uid
   *   The user uid.
   * @param string $entity_type
   *   The entity type.
   *
   * @return bool
   *   The result of the delete query.
   */
  protected function lockingDelete($entity_id, $langcode, $uid, $entity_type = 'node') {
    if (!$this->isTranslationLockEnabled($entity_type)) {
      $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    }
    $query = $this->database->delete('content_lock')
      ->condition('entity_type', $entity_type)
      ->condition('entity_id', $entity_id)
      ->condition('langcode', $langcode);
    if (!empty($uid)) {
      $query->condition('uid', $uid);
    }

    $result = $query->execute();

    return $result;
  }

  /**
   * Check if locking is verbose.
   *
   * @return bool
   *   Return true if locking is verbose.
   */
  public function verbose() {
    return $this->configFactory->get('content_lock.settings')->get('verbose');
  }

  /**
   * Try to lock a document for editing.
   *
   * @param int $entity_id
   *   The entity id.
   * @param string $langcode
   *   The translation language of the entity.
   * @param int $uid
   *   The user id to lock the node for.
   * @param string $entity_type
   *   The entity type.
   * @param bool $quiet
   *   Suppress any normal user messages.
   *
   * @return bool
   *   FALSE, if a document has already been locked by someone else.
   */
  public function locking($entity_id, $langcode, $uid, $entity_type = 'node', $quiet = FALSE) {
    $translation_lock = $this->isTranslationLockEnabled($entity_type);
    if (!$translation_lock) {
      $langcode = LanguageInterface::LANGCODE_NOT_SPECIFIED;
    }

    // Check locking status.
    $lock = $this->fetchLock($entity_id, $langcode, $entity_type);

    // No lock yet.
    if ($lock === FALSE || !is_object($lock)) {
      // Save locking into database.
      $this->lockingSave($entity_id, $langcode, $uid, $entity_type);

      if ($this->verbose() && !$quiet) {
        if ($translation_lock) {
          drupal_set_message($this->t('This content translation is now locked against simultaneous editing. This content translation will remain locked if you navigate away from this page without saving or unlocking it.'), 'status', FALSE);
        }
        else {
          drupal_set_message($this->t('This content is now locked against simultaneous editing. This content will remain locked if you navigate away from this page without saving or unlocking it.'), 'status', FALSE);
        }
      }
      // Post locking hook.
      $this->moduleHandler->invokeAll('content_lock_locked', [
        $entity_id,
        $langcode,
        $uid,
        $entity_type,
      ]);

      // Send success flag.
      return TRUE;
    }
    else {
      // Currently locking by other user.
      if ($lock->uid != $uid) {
        // Send message.
        $message = $this->displayLockOwner($lock, $translation_lock);
        drupal_set_message($message, 'warning');

        // Higher permission user can unblock.
        if ($this->currentUser->hasPermission('break content lock')) {

          $link = Link::createFromRoute(
            $this->t('Break lock'),
            'content_lock.break_lock.' . $entity_type,
            ['entity' => $entity_id, 'langcode' => $langcode],
            ['query' => ['destination' => $this->currentRequest->getRequestUri()]]
          )->toString();

          // Let user break lock.
          drupal_set_message($this->t('Click here to @link', ['@link' => $link]), 'warning');
        }

        // Return FALSE flag.
        return FALSE;
      }
      else {
        // Save locking into database.
        $this->lockingSave($entity_id, $langcode, $uid, $entity_type);

        // Locked by current user.
        if ($this->verbose() && !$quiet) {
          if ($translation_lock) {
            drupal_set_message($this->t('This content translation is now locked by you against simultaneous editing. This content translation will remain locked if you navigate away from this page without saving or unlocking it.'), 'status', FALSE);
          }
          else {
            drupal_set_message($this->t('This content is now locked by you against simultaneous editing. This content translation will remain locked if you navigate away from this page without saving or unlocking it.'), 'status', FALSE);
          }
        }

        // Send success flag.
        return TRUE;
      }
    }
  }

  /**
   * Check whether a node is configured to be protected by content_lock.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check.
   *
   * @return bool
   *   TRUE is entity is lockable
   */
  public function isLockable(EntityInterface $entity) {
    $entity_id = $entity->id();
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();

    $config = $this->configFactory->get('content_lock.settings')->get("types.$entity_type");

    $this->moduleHandler->invokeAll('content_lock_entity_lockable', [
      $entity,
      $entity_id,
      $entity->language()->getId(),
      $entity_type,
      $bundle,
      $config,
    ]);

    if (is_array($config) && in_array($bundle, $config)) {
      return TRUE;
    }

    // Always return FALSE.
    return FALSE;
  }

  /**
   * Builds a button class, link type form element to unlock the content.
   *
   * @param string $entity_type
   *   The entity type of the content.
   * @param int $entity_id
   *   The entity id of the content.
   * @param string $langcode
   *   The translation language code of the entity.
   * @param string $destination
   *   The destination query parameter to build the link with.
   *
   * @return array
   *   The link form element.
   */
  public function unlockButton($entity_type, $entity_id, $langcode, $destination) {
    $unlock_url_options = [];
    if ($destination) {
      $unlock_url_options['query'] = ['destination' => $destination];
    }
    $route_parameters = [
      'entity' => $entity_id,
      'langcode' => $this->isTranslationLockEnabled($entity_type) ? $langcode : LanguageInterface::LANGCODE_NOT_SPECIFIED,
    ];
    return [
      '#type' => 'link',
      '#title' => $this->t('Unlock'),
      '#access' => TRUE,
      '#attributes' => [
        'class' => ['button'],
      ],
      '#url' => Url::fromRoute('content_lock.break_lock.' . $entity_type, $route_parameters, $unlock_url_options),
      '#weight' => 200,
    ];
  }

  /**
   * Checks whether the entity type is lockable on translation level.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return bool
   *   TRUE if the entity type should be locked on translation level, FALSE if
   *   it should be locked on entity level.
   */
  public function isTranslationLockEnabled($entity_type_id) {
    return $this->moduleHandler->moduleExists('conflict') && in_array($entity_type_id, $this->configFactory->get('content_lock.settings')->get("types_translation_lock"));
  }

}
