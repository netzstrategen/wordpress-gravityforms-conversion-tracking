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
   * Option storing the last applied schema version.
   *
   * @var string
   */
  const SCHEMA_OPTION = 'gravityforms_conversion_tracking_schema';

  /**
   * Current schema version.
   *
   * @var int
   */
  const SCHEMA = 1;

  /**
   * @implements init
   */
  public static function init() {
    // Apply pending updates of stored form settings once after a plugin
    // update. Plugins are not loaded during `wp core update-db` and on
    // wp-admin/upgrade.php (WP_INSTALLING), so neither can trigger it; the
    // update code is only loaded when the stored schema is outdated.
    if ((int) get_option(static::SCHEMA_OPTION, 0) < static::SCHEMA) {
      Upgrade::run();
    }

    // Add lead-stage/value/currency and Conversion ID/Label fields to each
    // form's own Form Settings page.
    add_filter('gform_form_settings_fields', __NAMESPACE__ . '\FormSettings::addFields', 10, 2);

    // Append an inert tracking-data island to the (text) confirmation
    // markup of forms that have tracking configured.
    add_filter('gform_confirmation', __NAMESPACE__ . '\Confirmation::injectDataIsland', 10, 4);

    // Enqueue the script that reads that data island and fires the
    // dataLayer/gtag events, only for forms that need it.
    add_action('gform_enqueue_scripts', __NAMESPACE__ . '\Confirmation::enqueueScript', 10, 2);
  }

}
