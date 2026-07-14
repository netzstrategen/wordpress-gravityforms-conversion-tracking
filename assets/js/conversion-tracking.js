/**
 * Fires GA4/Google Ads lead-conversion events for Gravity Forms.
 *
 * This file runs only in the top-level document, never inside Gravity
 * Forms' hidden AJAX iframe: it reads the inert JSON "data island" that
 * Netzstrategen\GravityformsConversionTracking\Confirmation appends to a
 * form's text confirmation, and only then calls dataLayer/gtag.
 */
(function ($) {
  'use strict';

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
    window.dataLayer.push(payload);

    if (typeof window.gtag === 'function') {
      // GA4 side - reporting only, no Ads attribution.
      window.gtag('event', payload.event, {
        value: payload.value,
        currency: payload.currency
      });
      // Ads side - the actual conversion action, explicit destination.
      window.gtag('event', 'conversion', {
        send_to: payload.google_ads_conversion_id + '/' + payload.google_ads_conversion_label,
        value: payload.value,
        currency: payload.currency
      });
    }
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
