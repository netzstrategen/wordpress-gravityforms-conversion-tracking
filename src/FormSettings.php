<?php

/**
 * @file
 * Contains \Netzstrategen\GravityformsConversionTracking\FormSettings.
 */

namespace Netzstrategen\GravityformsConversionTracking;

/**
 * Adds Google Ads/GA4 conversion tracking fields to Form Settings.
 */
class FormSettings {

  /**
   * @implements gform_form_settings_fields
   */
  public static function addFields($fields, $form) {
    $fields['conversion_tracking'] = [
      'title' => esc_html__('Google Ads / GA4 Conversion Tracking', 'gravityforms-conversion-tracking'),
      'fields' => [
        [
          'name' => 'leadEventName',
          'type' => 'select',
          'label' => esc_html__('Lead stage', 'gravityforms-conversion-tracking'),
          'tooltip' => esc_html__('Which GA4 lead-funnel event this form submission represents. Select none to not send a GA4 event for this form.', 'gravityforms-conversion-tracking'),
          'default_value' => '',
          'choices' => [
            [
              'label' => esc_html__('None', 'gravityforms-conversion-tracking'),
              'value' => '',
            ],
            [
              'label' => esc_html__('General inquiry (generate_lead)', 'gravityforms-conversion-tracking'),
              'value' => 'generate_lead',
            ],
            [
              'label' => esc_html__('Qualified sales inquiry (qualify_lead)', 'gravityforms-conversion-tracking'),
              'value' => 'qualify_lead',
            ],
            [
              'label' => esc_html__('Customer support contact (working_lead)', 'gravityforms-conversion-tracking'),
              'value' => 'working_lead',
            ],
          ],
        ],
        [
          'name' => 'leadValue',
          'type' => 'text',
          'input_type' => 'number',
          'label' => esc_html__('Lead value', 'gravityforms-conversion-tracking'),
          'tooltip' => esc_html__('Monetary value of this lead. 0 is a valid value.', 'gravityforms-conversion-tracking'),
          'default_value' => '0',
          'class' => 'small',
        ],
        [
          'name' => 'leadCurrency',
          'type' => 'text',
          'label' => esc_html__('Currency', 'gravityforms-conversion-tracking'),
          'tooltip' => esc_html__('ISO 4217 currency code, e.g. EUR.', 'gravityforms-conversion-tracking'),
          'default_value' => 'EUR',
          'class' => 'small',
        ],
        [
          'name' => 'googleAdsConversionId',
          'type' => 'text',
          'label' => esc_html__('Google Ads Conversion ID', 'gravityforms-conversion-tracking'),
          'tooltip' => esc_html__('E.g. 123456789, with or without the AW- prefix. Leave this and the Conversion Label empty to not send a Google Ads conversion for this form.', 'gravityforms-conversion-tracking'),
          'class' => 'medium',
        ],
        [
          'name' => 'googleAdsConversionLabel',
          'type' => 'text',
          'label' => esc_html__('Google Ads Conversion Label', 'gravityforms-conversion-tracking'),
          'tooltip' => esc_html__('E.g. AbC-D_efG0h1I2j3K4. Copy and paste it from Google Ads instead of typing it: labels are case-sensitive, and 0 (zero) and O look alike.', 'gravityforms-conversion-tracking'),
          'class' => 'medium',
        ],
      ],
    ];
    return $fields;
  }

}
