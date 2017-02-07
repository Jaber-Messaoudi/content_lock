<?php

namespace Drupal\content_lock\Plugin\Views\Sort;

use Drupal\views\Plugin\views\sort\Standard;

/**
 * Content lock sort.
 *
 * @ViewsSort("content_lock_sort")
 */
class ContentLockSort extends Standard {
  /**
   * Query.
   */
  public function query() {
    $this->ensureMyTable();
    $this->query->addOrderBy($this->table_alias, 'timestamp', $this->options['order']);
  }
}
