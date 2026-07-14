# Gravity Forms Conversion Tracking plugin

## Context

Google Ads conversion tracking for Gravityforms leads currently requires, per
form: a custom "thank you" page, plus a bespoke GTM tag hardcoded with that
form's Conversion ID + Label, triggered by a bespoke Custom Event trigger.
Every new lead form means a new thank-you page *and* a new GTM tag/trigger
kept in sync — cumbersome, and already flagged as such in the linked Asana
task. Worse: forms using Gravity Forms' inline "text" confirmation (message
shown in place, no redirect/thank-you page — the default/most common setup
in current Gravity Forms versions, which submit via AJAX) fire **no**
conversion tracking at all today — that's the immediate bug driving this
work.

The fix: a small, portable plugin that lets an admin configure Google Ads +
GA4 lead-tracking parameters directly on each Gravityform's own Form
Settings page. On successful AJAX submission with a text confirmation, the
plugin fires a GA4 lead event (`generate_lead`/`qualify_lead`/`working_lead`,
selectable per form) plus the form's Ads conversion data — but critically,
**the actual event-firing JavaScript always executes in the top-level page,
never inside Gravity Forms' hidden AJAX iframe** (see Approach §3 — this
isn't just a privacy nicety, it also fixes a genuine double-firing bug the
naive "inline script in the confirmation HTML" approach would have caused).
A single, generic GTM tag (built once, manually, using GTM Data Layer
Variables) then handles Ads conversions for every form from then on — no
more per-form GTM changes.

The plugin must not depend on this site's `custom` plugin or `shop` theme,
since smaller sites (running Google Site Kit, sometimes without any GTM
container at all) have the identical need and should be able to reuse the
same plugin folder as-is.

Confirmed with the user: plugin lives in this repo for now, namespace
`Netzstrategen\GravityformsConversionTracking`; it emits both a dataLayer
push (GTM) and direct `gtag()` calls (non-GTM/Site Kit fallback); there's no
separate "enable tracking" checkbox — filling in both Conversion ID and
Label *is* the opt-in; AJAX submission (the modern GF default) is the
primary target, non-AJAX is supported by the same mechanism at no extra
cost.

## Approach

### 1. New plugin, 3-4 files, no build step

```
wp-content/plugins/gravityforms-conversion-tracking/
├── plugin.php                             # header, autoloader, hook registration
├── readme.txt                             # WordPress.org-style plugin readme
├── assets/js/conversion-tracking.js       # front-end: fires the actual event
└── src/
    ├── Plugin.php          # init() — hook registrations only, in execution order
    ├── FormSettings.php    # gform_form_settings_fields callback (admin UI)
    └── Confirmation.php    # gform_confirmation + gform_enqueue_scripts callbacks
```

Namespace `Netzstrategen\GravityformsConversionTracking` — no site-specific
prefix, since this plugin must stay portable to other sites, unlike
`Bnn\Custom`. Mirror this repo's existing conventions from
[custom.php](../custom/custom.php) and [Plugin.php](../custom/src/Plugin.php):
same plugin-header fields (Author/License), same PSR-4-ish
`spl_autoload_register` classloader mapping the namespace to `src/`, hooks
registered in one `init()`-style method in the literal order they run, each
with a short preceding comment. No activation/deactivation/uninstall hooks
are needed — the plugin only adds form-meta keys that Gravity Forms itself
already manages and cleans up when a form is deleted.

`Plugin::init()` registers, gated on Gravity Forms being active:
- `gform_form_settings_fields` → `FormSettings::addFields` (admin UI)
- `gform_confirmation` → `Confirmation::injectDataIsland` (emits inert data,
  not executable script — see §3)
- `gform_enqueue_scripts` → `Confirmation::enqueueScript` (loads the actual
  front-end logic, only for forms that have tracking configured)

### 2. Admin UI via the modern Settings API

Gravity Forms here is v2.10.3, so use `gform_form_settings_fields` (confirmed
at [form_settings.php:734](../gravityforms/form_settings.php:734)), adding
one new section with these fields, all camelCase to match GF core's own
field-naming convention:

| Field name | Type | Default | Notes |
|---|---|---|---|
| `googleAdsConversionId` | text | — | e.g. `AW-123456789` |
| `googleAdsConversionLabel` | text | — | e.g. `AbC-D_efG0h1I2j3K4` |
| `leadEventName` | select | `generate_lead` | choices: `generate_lead` ("General inquiry"), `qualify_lead` ("Qualified sales inquiry"), `working_lead` ("Customer support contact") — see §6 for why these three |
| `leadValue` | text | `0` | monetary value; `0` is a valid, accepted value — see gotcha below |
| `leadCurrency` | text | `EUR` | ISO 4217 code |

Confirmed at [form_settings.php:889-902](../gravityforms/form_settings.php:889-902):
GF's Settings API auto-persists any field returned by this filter straight
onto `$form[$field['name']]` on save — **no separate save/sanitize hook is
needed**; the built-in `text`/`select` field types already sanitize posted
values before save. This is simpler than the legacy `gform_form_settings`
approach the old, currently-active MediaRon plugin uses
([GFGAET_Pagination_Settings.php:36](../gravity-forms-google-analytics-event-tracking/includes/GFGAET_Pagination_Settings.php:36)).

**Gotcha**: when reading `leadValue` back, use `is_numeric()`/`trim()`
rather than `empty()` — PHP's `empty('0')` is `true`, which would wrongly
treat an intentional `0` value as "not set."

### 3. Emission: inert data island + top-level-only script (no code runs inside the iframe)

Only "text"-type confirmations are in scope (redirect confirmations are
explicitly out of scope). The opt-in gate for the whole feature stays: fire
only when **both** `googleAdsConversionId` and `googleAdsConversionLabel`
are non-empty on `$form` — simplest correct behavior, avoids a
half-configured state that's hard to notice in the admin UI.

**Why not an inline `<script>` in the confirmation content** (the original,
now-superseded design, and the technique the currently-active MediaRon
plugin uses): a targeted investigation of Gravity Forms' own AJAX transport
(`wp-content/plugins/gravityforms/form_display.php`) found that it still
uses a hidden iframe (`gform_ajax_frame_{id}`, confirmed at
[form_display.php:1246](../gravityforms/form_display.php:1246) and
[:1469](../gravityforms/form_display.php:1469)) which genuinely *navigates*
to a full server-rendered HTML document (`get_ajax_postback_html()`,
[form_display.php:5435](../gravityforms/form_display.php:5435)). Any inline
`<script>` in that response executes once, inside the iframe's own document,
as the browser parses it. Then, when the parent frame's own JS swaps the
confirmation into the visible page
([form_display.php:1447-1453](../gravityforms/form_display.php:1447-1453):
`.contents().find('.GF_AJAX_POSTBACK').html()` → `jQuery('#gform_wrapper_{id}').replaceWith(confirmation_content)`),
**jQuery's `.replaceWith()`/`.html()` detect and re-execute plain `<script>`
tags a second time** in the parent context. So a naive `window.parent.dataLayer.push(...)`
script would genuinely **fire twice** — once from inside the iframe, once
again after the parent-side swap — silently double-counting every single
conversion. This is a real correctness bug, not just an architectural
preference, and it's exactly what the user's instinct to avoid iframe script
execution heads off.

**Design instead**: `Confirmation::injectDataIsland()` (hooked to
`gform_confirmation`, signature confirmed at
[form_display.php:2213-2274](../gravityforms/form_display.php:2213-2274))
appends an **inert** data island, never an executable script:
```html
<script type="application/json" class="gfct-tracking-data" data-form-id="12">
{"event":"generate_lead","form_id":12,"form_title":"...","value":0,"currency":"EUR","google_ads_conversion_id":"AW-123456789","google_ads_conversion_label":"AbC-D_efG0h1I2j3K4"}
</script>
```
`type="application/json"` is never parsed/executed as JS by any browser, in
any context, regardless of how it's inserted into the DOM — confirmed
nothing in GF's swap path strips or special-cases such tags, so it survives
both the iframe navigation and the jQuery re-insertion completely inert,
landing safely in the parent document exactly once.

A small separate static asset, `assets/js/conversion-tracking.js` (enqueued
by `Confirmation::enqueueScript()`, hooked to `gform_enqueue_scripts` —
confirmed as a real, per-form-firing action at
[form_display.php:3321](../gravityforms/form_display.php:3321),
`gf_do_action(['gform_enqueue_scripts', $form['id']], $form, $ajax)` — only
enqueue when the current `$form` has tracking configured), does the actual
firing, **always in the genuine top-level window/document**:
- Binds `jQuery(document).on('gform_confirmation_loaded', function(e, formId) { ... })`
  — this is the correct, documented signal for the AJAX path (confirmed
  fired only on the parent document, as the last step after the swap, at
  [form_display.php:1453](../gravityforms/form_display.php:1453)) — and
  reads the matching `.gfct-tracking-data[data-form-id="formId"]` island
  now present in the DOM.
- Also runs an immediate scan for any such island on script load, covering
  the non-AJAX case (full page reload, no `gform_confirmation_loaded` event
  fires at all, but the island is already present in the initial HTML).
- Marks a processed island (`data-gfct-fired`) to stay idempotent.
- One shared function then does the actual `window.dataLayer.push(...)` (no
  `window.parent` trickery needed anymore — this code only ever runs in the
  top-level document) and, if `window.gtag` exists, the `gtag()` fallback
  calls (see §4).

### 4. Event payload + gtag calls

**dataLayer push** (GTM path):
```json
{
  "event": "generate_lead",
  "form_id": 12,
  "form_title": "Kontaktformular Werbemarkt",
  "value": 0,
  "currency": "EUR",
  "google_ads_conversion_id": "AW-123456789",
  "google_ads_conversion_label": "AbC-D_efG0h1I2j3K4"
}
```
`event` is whichever of `generate_lead`/`qualify_lead`/`working_lead` was
selected per form (see §6 for GA4's exact documented parameters for these).

**gtag fallback** (only if `window.gtag` exists — Site Kit / gtag.js-only
installs, no GTM container). Two separate calls are required, not one —
see §7 for why:
```js
// GA4 side — reporting only, no Ads attribution.
gtag('event', 'generate_lead', { value: 0, currency: 'EUR' });
// Ads side — the actual conversion action, explicit destination + value.
gtag('event', 'conversion', {
  send_to: 'AW-123456789/AbC-D_efG0h1I2j3K4',
  value: 0,
  currency: 'EUR',
});
```

Build the JSON island through a single `wp_json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP)`
call (the same flags Gravity Forms core itself uses for its own inline
redirect script) — guarantees no `<`/`>`/`&` in any field (e.g. an unusual
form title) can break out of the surrounding `<script>` tag. No entry/user-
submitted field values are ever placed in the payload, only form-level
settings plus form id/title — keep it that way.

### 5. One-time GTM configuration (manual, outside this plugin/repo)

Document this for the user as a required follow-up once the plugin is live:
1. Data Layer Variables: `google_ads_conversion_id`, `google_ads_conversion_label`,
   `value`, `currency` (plus optionally `form_id`/`form_title` for debugging).
2. A Custom Event trigger matching event name(s) `generate_lead` OR
   `qualify_lead` OR `working_lead` (a "Custom Event" trigger supports a
   regex match, e.g. `^(generate|qualify|working)_lead$`).
3. One generic "Google Ads Conversion Tracking" tag, Conversion ID/Label/
   Value/Currency wired to the Data Layer Variables above, firing on that
   trigger — replaces every current per-form tag.
4. A GA4 Event tag (event name = `{{Event}}`, i.e. dynamically the same
   name that fired) bound to the same trigger, so GA4 actually receives
   `generate_lead`/`qualify_lead`/`working_lead` — don't assume the existing
   GA4 Configuration tag's "send all events" setting already covers this;
   verify it.
5. Verify everything in GTM Preview mode before publishing (see §8).

### 6. Which GA4 lead events, and their parameters

Per Google's GA4 event reference, the lead-funnel events are `generate_lead`,
`qualify_lead`, `working_lead`, `disqualify_lead`, `close_convert_lead`, and
`close_unconvert_lead` — all documented with `currency` + `value` as their
core parameters (GA4 doesn't hard-reject an event missing them, but they're
required for the event to be interpretable in revenue/lead-value reporting,
which is the entire point of sending them). Restricting the per-form choice
to three (`generate_lead`, `qualify_lead`, `working_lead`) per the user's
own mapping (general inquiry / concrete sales inquiry / customer-support
contact) is a reasonable, deliberately narrowed subset — the other three
events describe *later* funnel stages (disqualification, final conversion)
that don't correspond to "a Gravityform was just submitted" and would need
a different data source (CRM/backend) to fire correctly; not in scope here.

### 7. Best-practice check: does this whole approach make sense?

Yes, with one caution to flag to the user. Current guidance (2026) is to use
a **native Google Ads conversion tag** — via GTM's "Google Ads Conversion
Tracking" tag type, or a direct `gtag('event', 'conversion', {send_to: ...})`
call — as the *primary* signal for Ads bidding/attribution, because it
reaches Google Ads within hours. Importing a GA4 Key Event (e.g.
`generate_lead`) into Google Ads as a secondary conversion source is a valid
complementary approach, but is slower (1-3 day data latency) and should
**not** be used as a duplicate/second conversion action for the *same* form
submission the native Ads tag already tracks — that double-counts
conversions in the Ads account. This plugin's design already matches the
recommended shape: a native Ads conversion tag (fed by explicit Conversion
ID/Label from our dataLayer) plus a separate GA4 event for GA4's own
reporting/audiences — just make sure nobody *also* sets up a GA4→Ads Key
Event import for `generate_lead` once the native tag from §5 is live.

**Conversion Linker and how parameters get "picked up" by Ads**: Conversion
Linker only captures the ad-click identifier (`gclid`/`gbraid`/`wbraid` from
the landing-page URL) into first-party cookies (`_gcl_aw` etc.) — it has
nothing to do with reading our event's parameters. It runs continuously,
independent of any lead event, so that *whenever* a conversion tag later
fires, Ads can attribute it back to the click that brought the visitor in.
The actual Conversion ID/Label/Value/Currency are never "auto-picked-up" —
they must always be explicitly supplied to the conversion tag/call (via GTM
Data Layer Variables, as designed in §5, or via the explicit `gtag('event',
'conversion', {...})` params in the fallback path). Conversion Linker and
our dataLayer push solve two different problems that combine at
conversion-tag-fire-time: "which click do we credit" (Linker) and "what
happened, how much is it worth" (our event).

**Non-GTM / Site Kit-only sites**: when Site Kit connects a Google Ads
account without a GTM container, it still loads a single `gtag.js` snippet
configured with `gtag('config', 'AW-XXXXXXXXX')` (and `G-XXXXXXX` for GA4,
if linked). That `AW-...` config call *is* the gtag.js equivalent of
Conversion Linker — it automatically enables auto-tagging/click-ID capture
on load, no separate tag needed. But, exactly as with GTM, the actual
conversion still needs an explicit event call with `send_to` set to that
site's Conversion ID/Label — which is exactly what the `gtag()` fallback in
§4 provides, making this plugin work identically well on GTM and non-GTM
(Site Kit) sites without extra per-site logic.

### 8. Verification

1. On a low-traffic test form with a text confirmation and AJAX on (the
   default): fill in Conversion ID/Label/lead-stage/value/currency in Form
   Settings, confirm the section renders and saves (including saving `0` as
   the value and re-opening the settings page to confirm it wasn't coerced
   to empty).
2. Submit the form (AJAX). In DevTools, confirm the `.gfct-tracking-data`
   `<script type="application/json">` island appears exactly once in the
   final DOM after the swap, and `window.dataLayer` shows exactly **one**
   pushed object (verifying the double-fire bug from §3 is actually fixed,
   not just theoretically).
3. Repeat with AJAX off on a second form — confirm the same single dataLayer
   push happens via the immediate-scan path, no `gform_confirmation_loaded`
   needed.
4. Stub `window.gtag = console.log` before a test submission and confirm
   both fallback calls fire (`generate_lead` and `conversion`, with the
   right `send_to`/`value`/`currency`).
5. Leave Conversion ID/Label empty on a form and confirm no data island and
   no dataLayer push happen at all (opt-in gate).
6. Once the one-time GTM setup (§5) is live, use GTM Preview mode: submit
   the test form, confirm the lead event appears as fired, the Data Layer
   Variables resolve correctly, and both the Ads Conversion tag and GA4
   Event tag show as Fired — exactly once each.
7. Repeat on a second form with different Conversion ID/Label/value/currency
   with zero further GTM changes — the core acceptance criterion.
8. Confirm the existing, still-active
   `gravity-forms-google-analytics-event-tracking` plugin is unaffected
   (different `event` name, no collision) — a candidate for later
   retirement once this plugin covers lead forms, not part of this task.
