=== Gravity Forms Conversion Tracking ===
Contributors: tha_sun, netzstrategen
Requires Plugins: gravityforms
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0

Per-form Google Ads and GA4 lead-conversion tracking for Gravity Forms text confirmations.

== Description ==

Adds a "Google Ads / GA4 Conversion Tracking" section to every Gravity
Forms form's own Form Settings page (Conversion ID, Conversion Label, lead
stage, value, currency). When that form is submitted and its text
confirmation is shown, the plugin sends:

* the GA4 recommended lead event (`generate_lead`, `qualify_lead` or
  `working_lead`, as selected per form) with `value` and `currency`,
* the Google Ads conversion (`send_to: 'AW-{Conversion ID}/{Conversion Label}'`)
  with `value` and `currency`,

both as `gtag()` commands, which are processed by the Google tags on the
page — whether those are embedded via `gtag.js` (e.g. by Google Site Kit)
or deployed via Google Tag Manager. No per-form tags, triggers or thank-you
pages are needed.

Additionally, the lead event is pushed to `window.dataLayer` as a standard
event with all parameters (`{event: 'generate_lead', form_id: …}`), for
custom tags and third-party tools that pick up standard events from the
dataLayer (see "Tracking setup").

The events are always sent from the top-level page, never from inside
Gravity Forms' hidden AJAX iframe — see SPEC.md for why that distinction
matters (it avoids a genuine double-counting bug).

Only text confirmations are supported. Forms with a Page or Redirect
confirmation are not tracked.

== Requirements ==

* [Gravity Forms](https://www.gravityforms.com/) 2.5 or later — this
  plugin relies on the modern Form Settings API
  (`gform_form_settings_fields`).
* A Google tag for the Google Ads account and one for GA4 on the front end,
  embedded either via Google Tag Manager or directly via `gtag.js`.

== Installation ==

1. Upload the `gravityforms-conversion-tracking` folder to
   `wp-content/plugins/`.
2. Activate the plugin. Gravity Forms must already be active; the plugin
   does nothing otherwise.
3. Complete the one-time tracking setup below.
4. Configure each form, see "Form settings" below.

== Tracking setup ==

= Google Tag Manager =

The plugin sends its events as `gtag()` commands, which the Google tags
deployed in the container process directly. The container therefore only
needs the following tags, which most containers already have:

1. **Google tag for Google Ads**: Tags → New → Tag type "Google tag",
   Tag ID `AW-XXXXXXXXX` (your Google Ads Conversion ID). Trigger: all pages,
   or your consent-aware equivalent. Without this tag, conversions are not
   sent to Google Ads.
2. **Conversion Linker**: Tags → New → Tag type "Conversion Linker".
   Trigger: all pages. It has no conversion settings and needs no variables;
   it only stores the ad-click ID from landing-page URLs in first-party
   cookies, so that a later conversion can be attributed to that click.
3. **Google tag for GA4**: Tag type "Google tag", Tag ID `G-XXXXXXXXXX`.
   Trigger: all pages.

Do **not** add "Google Ads Conversion Tracking" or "GA4 Event" tags for the
plugin's events — the Google tags above already receive them, so every lead
would be counted twice. Likewise, remove any per-form conversion tags that
fired on thank-you pages of forms that now use this plugin.

To verify, open Tag Assistant (GTM Preview) and consent to tracking in the
site's consent banner, then submit a configured form. The event list should
show the lead event (e.g. `generate_lead`) twice — the dataLayer event and
the `gtag()` command — followed by `conversion`, and the Google Ads/GA4
destinations should list the corresponding hits.

Optional — custom tags: to forward the lead to other tools, create a Custom
Event trigger for the lead event name and Data Layer Variables for any of
its keys: `form_id`, `form_title`, `value`, `currency`,
`google_ads_conversion_id`, `google_ads_conversion_label`,
`google_ads_send_to`. Because Google Tag Manager also lists the `gtag()`
command of the same name as an event, such a trigger matches twice per
submission; check in Tag Assistant that your tags fire only once.

= gtag.js (e.g. Google Site Kit, without Google Tag Manager) =

The page must configure the Google Ads account and the GA4 property, i.e.
call `gtag('config', 'AW-XXXXXXXXX')` and `gtag('config', 'G-XXXXXXXXXX')`.
In Google Site Kit, connect Analytics and enter the Ads Conversion ID in
the Ads module settings. No further setup is needed; the Google Ads tag
stores ad-click IDs itself, equivalent to the Conversion Linker.

= Google Analytics =

The lead events arrive in GA4 without further setup. To report them as
conversions, mark them as key events in GA4 (Admin → Events). Do not
additionally import them into Google Ads as conversions: the Google Ads
conversion is already sent directly, and importing it again would count
every lead twice.

== Form settings ==

On a form's Settings → Form Settings page, section "Google Ads / GA4
Conversion Tracking":

* **Google Ads Conversion ID** and **Conversion Label**: in Google Ads,
  open Goals → Conversions → Summary → the conversion action → Tag setup →
  "Use Google Tag Manager", and copy both values from there. The ID may be
  entered with or without the `AW-` prefix. Paste the label instead of
  typing it: labels are case-sensitive, and `0` (zero) and `O` look alike.
  Tracking is only active when both fields are filled in.
* **Lead stage**: the GA4 lead event to send — `generate_lead` (general
  inquiry), `qualify_lead` (qualified sales inquiry) or `working_lead`
  (customer support contact).
* **Lead value** and **Currency**: sent with both the GA4 event and the
  Google Ads conversion; `0` is valid. Currency defaults to `EUR`.

The form's confirmation must be of type "Text".

== Changelog ==

= 1.1.0 =
* Google Ads conversion and GA4 lead event are sent as gtag() commands even
  if the page does not define window.gtag, so that Google tags deployed via
  Google Tag Manager receive them without further tags or triggers.
* Conversion ID is accepted with or without the `AW-` prefix. The dataLayer
  event carries the bare number in `google_ads_conversion_id` and the
  prefixed `AW-{id}/{label}` in the new `google_ads_send_to`.

= 1.0.0 =
* Initial release.
