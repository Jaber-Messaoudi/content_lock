/**
 * @file
 * Defines Javascript behaviors for the content lock module.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  /**
   * Behaviors for the content lock settings form.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the contnet lock settings form behavior.
   */
  Drupal.behaviors.contentLockSettings = {
    attach: function (context, settings) {
      $('.content-lock-entity-settings [value="*"]', context)
        .once('content-lock-settings')
        // Init
        .each(Drupal.behaviors.contentLockSettings.toogleBundles)
        // Change
        .change(Drupal.behaviors.contentLockSettings.toogleBundles);
    },

    toogleBundles: function () {
      var all_bundles_selected = this.checked;
      $(this).parent('.form-type-checkbox').siblings().each(function () {
        // If the "All bundles" checkbox is checked then uncheck and disable
        // all other options.
        var $checkbox = $('[type="checkbox"]', this);
        if (all_bundles_selected) {
          $checkbox
            .prop('disabled', true)
            .prop('checked', false)
            .addClass('is-disabled');
          $(this).hide();
        }
        else {
          $checkbox
            .prop('disabled', false)
            .removeClass('is-disabled');
          $(this).show();
        }
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
