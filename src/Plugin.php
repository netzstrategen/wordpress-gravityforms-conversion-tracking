<?php

/**
 * @file
 * Contains \Netzstrategen\GravityformsConversionTracking\Plugin.
 */

namespace Netzstrategen\GravityformsConversionTracking;

/**
 * Hook registrations.
 */
class Plugin {

  /**
   * @implements init
   */
  public static function init() {
    // Add Conversion ID/Label/lead-stage/value/currency fields to each
    // form's own Form Settings page.
    add_filter('gform_form_settings_fields', __NAMESPACE__ . '\FormSettings::addFields', 10, 2);

    // Append an inert tracking-data island to the (text) confirmation
    // markup of forms that have both Conversion ID and Label configured.
    add_filter('gform_confirmation', __NAMESPACE__ . '\Confirmation::injectDataIsland', 10, 4);

    // Enqueue the script that reads that data island and fires the
    // dataLayer/gtag events, only for forms that need it.
    add_action('gform_enqueue_scripts', __NAMESPACE__ . '\Confirmation::enqueueScript', 10, 2);
  }

}
