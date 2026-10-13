<?php

/**
 * @file
 * Contains \Netzstrategen\GravityformsConversionTracking\Upgrade.
 */

namespace Netzstrategen\GravityformsConversionTracking;

use GFAPI;
use GFFormsModel;

/**
 * Migrates stored form settings to the current plugin version.
 */
class Upgrade {

  /**
   * Applies all pending updates.
   */
  public static function run() {
    $schema = (int) get_option(Plugin::SCHEMA_OPTION, 0);
    if ($schema < 1) {
      static::removeUnusedLeadStages();
    }
    update_option(Plugin::SCHEMA_OPTION, Plugin::SCHEMA, TRUE);
  }

  /**
   * Removes the lead stage from forms without a Google Ads conversion.
   *
   * Up to 1.1.x, the lead stage defaulted to generate_lead and was stored
   * with every form whose settings were saved, but it had no effect unless
   * the Conversion ID and Label were filled in as well. Since 1.2.0, a lead
   * stage alone sends the GA4 lead event, so these stored defaults would
   * start sending GA4 events for forms that were never configured.
   */
  public static function removeUnusedLeadStages() {
    foreach (GFFormsModel::get_form_ids(NULL, NULL) as $formId) {
      $form = GFAPI::get_form($formId);
      if (!$form || (string) rgar($form, 'leadEventName') === '') {
        continue;
      }
      $conversionId = trim((string) rgar($form, 'googleAdsConversionId'));
      $conversionLabel = trim((string) rgar($form, 'googleAdsConversionLabel'));
      if ($conversionId !== '' && $conversionLabel !== '') {
        continue;
      }
      unset($form['leadEventName']);
      GFAPI::update_form($form);
    }
  }

}
