<?php

/**
 * @file
 * Contains \Netzstrategen\GravityformsConversionTracking\Confirmation.
 */

namespace Netzstrategen\GravityformsConversionTracking;

/**
 * Emits GA4/Google Ads lead-conversion tracking data for configured forms.
 */
class Confirmation {

  /**
   * CSS class marking the tracking-data island appended to confirmations.
   *
   * @var string
   */
  const ISLAND_CLASS = 'gfct-tracking-data';

  /**
   * @implements gform_confirmation
   */
  public static function injectDataIsland($confirmation, $form, $entry, $ajax) {
    // Redirect-type confirmations return an array; only text confirmations
    // are supported.
    if (!is_string($confirmation) || $confirmation === '') {
      return $confirmation;
    }

    $payload = static::buildPayload($form);
    if ($payload === NULL) {
      return $confirmation;
    }

    // type="application/json" keeps this inert: it never executes as JS,
    // in the AJAX iframe or after Gravity Forms swaps it into the parent
    // document, unlike a plain <script>, which would otherwise run once
    // inside the iframe and then a second time when jQuery re-inserts the
    // confirmation markup - silently double-firing every conversion.
    $json = wp_json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP);
    $island = '<script type="application/json" class="' . static::ISLAND_CLASS . '" data-form-id="' . (int) rgar($form, 'id') . '">' . $json . '</script>';
    return $confirmation . $island;
  }

  /**
   * @implements gform_enqueue_scripts
   */
  public static function enqueueScript($form, $ajax) {
    if (static::buildPayload($form) === NULL) {
      return;
    }

    $file = dirname(__DIR__) . '/assets/js/conversion-tracking.js';
    wp_enqueue_script(
      'gravityforms-conversion-tracking',
      plugins_url('assets/js/conversion-tracking.js', FILE),
      ['jquery'],
      file_exists($file) ? filemtime($file) : NULL,
      TRUE
    );
  }

  /**
   * Builds the tracking payload for a form, or NULL if tracking is not
   * configured for it.
   *
   * Presence of both the Conversion ID and Label is the single opt-in
   * signal for the whole feature; the lead stage/value/currency fields
   * only shape what gets fired once opted in.
   */
  protected static function buildPayload($form) {
    // Google Ads shows the Conversion ID with or without its "AW-" prefix,
    // depending on the screen it is copied from. GTM's Google Ads Conversion
    // Tracking tag expects the bare number, gtag() the prefixed form.
    $conversionId = preg_replace('@^AW-@i', '', trim((string) rgar($form, 'googleAdsConversionId')));
    $conversionLabel = trim((string) rgar($form, 'googleAdsConversionLabel'));
    if ($conversionId === '' || $conversionLabel === '') {
      return NULL;
    }

    $eventName = (string) rgar($form, 'leadEventName');
    if (!in_array($eventName, ['generate_lead', 'qualify_lead', 'working_lead'], TRUE)) {
      $eventName = 'generate_lead';
    }

    // is_numeric(), not empty(): '0' is a valid, intentional lead value and
    // must not be coerced to "not set".
    $value = rgar($form, 'leadValue');
    $value = is_numeric($value) ? (float) $value : 0.0;

    $currency = trim((string) rgar($form, 'leadCurrency'));
    if ($currency === '') {
      $currency = 'EUR';
    }

    return [
      'event' => $eventName,
      'form_id' => (int) rgar($form, 'id'),
      'form_title' => (string) rgar($form, 'title'),
      'value' => $value,
      'currency' => $currency,
      'google_ads_conversion_id' => $conversionId,
      'google_ads_conversion_label' => $conversionLabel,
      'google_ads_send_to' => 'AW-' . $conversionId . '/' . $conversionLabel,
    ];
  }

}
