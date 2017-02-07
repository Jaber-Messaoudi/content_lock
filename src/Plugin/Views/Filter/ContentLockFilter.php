<?php

namespace Drupal\content_lock\Plugin\Views\Filter;

use Drupal\views\Plugin\views\filter\BooleanOperator;

/**
 *
 * @group views_filter_handlers
 *
 * @ViewsFilter("content_lock_filter")
 */
class ContentLockFilter extends BooleanOperator {
  /**
   * Query.
   */
  public function query() {
    $this->ensureMyTable();
    if (empty($this->value)) {
      $this->query->addWhere($this->options['group'], $this->tableAlias . ".timestamp", "NULL", "=");
    }
    else {
      $this->query->addWhere($this->options['group'], $this->tableAlias . ".timestamp", "NULL", "<>");
    }
  }
}
