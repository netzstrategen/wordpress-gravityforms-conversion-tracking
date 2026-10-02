/**
 * Fires GA4/Google Ads lead-conversion events for Gravity Forms.
 *
 * This file runs only in the top-level document, never inside Gravity
 * Forms' hidden AJAX iframe: it reads the inert JSON "data island" that
 * Netzstrategen\GravityformsConversionTracking\Confirmation appends to a
 * form's text confirmation, and only then pushes to the dataLayer.
 */
(function ($) {
  'use strict';

  // Google tags process gtag() commands from the dataLayer, whether they are
  // embedded via gtag.js or deployed via Google Tag Manager - in the latter
  // case, the page usually does not define window.gtag itself. A command
  // must be pushed as an Arguments object, not as an array.
  function gtagCommand() {
    if (typeof window.gtag === 'function') {
      window.gtag.apply(window, arguments);
    }
    else {
      window.dataLayer.push(arguments);
    }
  }

  // Returns the IDs of all loaded GA4 Google tags. A gtag() command without
  // send_to only reaches Google tags configured via gtag('config') on the page
  // itself, not Google tags deployed via Google Tag Manager. Google's tag
  // library registers every loaded Google tag in window.google_tag_manager.
  function ga4Destinations() {
    return Object.keys(window.google_tag_manager || {}).filter(function (id) {
      return /^G-/.test(id);
    });
  }

  function fireEvent(island) {
    if (island.getAttribute('data-gfct-fired')) {
      return;
    }
    island.setAttribute('data-gfct-fired', '1');

    var payload;
    try {
      payload = JSON.parse(island.textContent);
    }
    catch (e) {
      return;
    }

    window.dataLayer = window.dataLayer || [];

    // Standard lead event with all parameters, for custom tags and third-party
    // tools reading standard events from the dataLayer.
    window.dataLayer.push(payload);

    // GA4: recommended lead event. Falls back to the default routing to all
    // configured Google tags if no GA4 Google tag is detected.
    var ga4Params = {
      value: payload.value,
      currency: payload.currency
    };
    var ga4Ids = ga4Destinations();
    if (ga4Ids.length) {
      ga4Params.send_to = ga4Ids;
    }
    gtagCommand('event', payload.event, ga4Params);

    // Google Ads: the conversion action itself. Only sent once the Google tag
    // for the Ads account is loaded, i.e. subject to its consent handling.
    gtagCommand('event', 'conversion', {
      send_to: payload.google_ads_send_to,
      value: payload.value,
      currency: payload.currency
    });
  }

  function fireForForm(formId) {
    var selector = '.gfct-tracking-data[data-form-id="' + formId + '"]';
    document.querySelectorAll(selector).forEach(fireEvent);
  }

  // Non-AJAX confirmations: the island is already part of the initial
  // page load; no gform_confirmation_loaded event fires at all.
  document.querySelectorAll('.gfct-tracking-data').forEach(fireEvent);

  // AJAX confirmations: fires only after Gravity Forms has swapped the
  // confirmation markup into the top-level document.
  $(document).on('gform_confirmation_loaded', function (event, formId) {
    fireForForm(formId);
  });

}(jQuery));
