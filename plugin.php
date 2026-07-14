<?php

/*
  Plugin Name: Gravity Forms Conversion Tracking
  Version: 1.0.0
  Requires Plugins: gravityforms
  Text Domain: gravityforms-conversion-tracking
  Description: Per-form Google Ads/GA4 lead-conversion tracking for Gravity Forms text confirmations.
  Author: Daniel F. Kudwien (sun)
  Author URI: http://www.netzstrategen.com/sind/daniel-kudwien
  License: GPL-2.0+
  License URI: http://www.gnu.org/licenses/gpl-2.0
*/

namespace Netzstrategen\GravityformsConversionTracking;

if (!defined('ABSPATH')) {
  header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
  exit;
}

define(__NAMESPACE__ . '\FILE', __FILE__);

/**
 * Loads PSR-4-style plugin classes.
 */
function classloader($class) {
  static $ns_offset;
  if (strpos($class, __NAMESPACE__ . '\\') === 0) {
    if ($ns_offset === NULL) {
      $ns_offset = strlen(__NAMESPACE__) + 1;
    }
    include __DIR__ . '/src/' . strtr(substr($class, $ns_offset), '\\', '/') . '.php';
  }
}
spl_autoload_register(__NAMESPACE__ . '\classloader');

// Bail out if Gravity Forms is not active; this plugin has no purpose
// without it. Checked on plugins_loaded rather than here directly, since
// plugin load order is not guaranteed and Gravity Forms' own main file may
// not have run yet at this point.
add_action('plugins_loaded', function () {
  if (!class_exists('GFForms')) {
    return;
  }
  add_action('init', __NAMESPACE__ . '\Plugin::init');
});
