=== Gravity Forms Conversion Tracking ===
Contributors: tha_sun, netzstrategen
Requires Plugins: gravityforms
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0

Per-form Google Ads and GA4 lead-conversion tracking for Gravity Forms text confirmations.

== Description ==

Adds a "Google Ads / GA4 Conversion Tracking" section to every Gravity
Forms form's own Form Settings page (Conversion ID, Conversion Label, lead
stage, value, currency). When that form is submitted and its text
confirmation is shown, the plugin fires a GA4 lead event
(`generate_lead`/`qualify_lead`/`working_lead`) plus the form's Google Ads
conversion data into `window.dataLayer`, and, where `gtag.js` is present
directly (e.g. via Google Site Kit without Google Tag Manager), calls
`gtag()` directly as a fallback.

The event is always fired from the top-level page, never from inside
Gravity Forms' hidden AJAX iframe — see SPEC.md for why that distinction
matters (it avoids a genuine double-counting bug).

A single, generic Google Tag Manager tag (built once, manually, using GTM
Data Layer Variables) can then handle Google Ads conversions for every
configured form, without any further per-form GTM changes. See SPEC.md for
the exact one-time GTM setup this plugin expects.

== Requirements ==

* [Gravity Forms](https://www.gravityforms.com/) 2.5 or later — this
  plugin relies on the modern Form Settings API
  (`gform_form_settings_fields`).
* Either a Google Tag Manager container, or a directly embedded Google
  `gtag.js` snippet (e.g. via Google Site Kit) on the front end.

== Installation ==

1. Upload the `gravityforms-conversion-tracking` folder to
   `wp-content/plugins/`.
2. Activate the plugin. Gravity Forms must already be active; the plugin
   does nothing otherwise.
3. Open a form's Form Settings page and fill in the "Google Ads / GA4
   Conversion Tracking" section. Both Conversion ID and Conversion Label
   must be filled in for tracking to fire for that form.
4. Complete the one-time Google Tag Manager (or `gtag.js`) setup described
   in SPEC.md.

== Changelog ==

= 1.0.0 =
* Initial release.
