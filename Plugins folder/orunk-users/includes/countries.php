<?php
/**
 * Orunk Users Country Helper
 *
 * Provides a function to retrieve a list of countries.
 *
 * @package OrunkUsers\Includes
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Retrieves a list of countries.
 *
 * Returns an array mapping ISO 3166-1 Alpha-2 codes to country names.
 * The list can be filtered using 'orunk_countries_list'.
 *
 * @return array Associative array of countries [Code => Name].
 */
function orunk_get_countries() {
    // Define the list of countries (Code => Name)
    $countries = array(
        ''   => __('Select a Country...', 'orunk-users'), // Placeholder option
        'US' => __('United States', 'orunk-users'),
        'CN' => __('China', 'orunk-users'),
        'IN' => __('India', 'orunk-users'),
        'ID' => __('Indonesia', 'orunk-users'),
        'PK' => __('Pakistan', 'orunk-users'),
        'BR' => __('Brazil', 'orunk-users'),
        'NG' => __('Nigeria', 'orunk-users'),
        'BD' => __('Bangladesh', 'orunk-users'),
        'RU' => __('Russia', 'orunk-users'),
        'MX' => __('Mexico', 'orunk-users'),
        'JP' => __('Japan', 'orunk-users'),
        'ET' => __('Ethiopia', 'orunk-users'),
        'PH' => __('Philippines', 'orunk-users'),
        'EG' => __('Egypt', 'orunk-users'),
        'VN' => __('Vietnam', 'orunk-users'),
        'DR' => __('Democratic Republic of the Congo', 'orunk-users'),
        'TR' => __('Turkey', 'orunk-users'),
        'IR' => __('Iran', 'orunk-users'),
        'DE' => __('Germany', 'orunk-users'),
        'TH' => __('Thailand', 'orunk-users'),
        'GB' => __('United Kingdom', 'orunk-users'),
        'FR' => __('France', 'orunk-users'),
        'IT' => __('Italy', 'orunk-users'),
        'ZA' => __('South Africa', 'orunk-users'),
        'KR' => __('South Korea', 'orunk-users'),
        'CO' => __('Colombia', 'orunk-users'),
        'ES' => __('Spain', 'orunk-users'),
        'UA' => __('Ukraine', 'orunk-users'),
        'AR' => __('Argentina', 'orunk-users'),
        'DZ' => __('Algeria', 'orunk-users'),
        'SD' => __('Sudan', 'orunk-users'),
        'IQ' => __('Iraq', 'orunk-users'),
        'AF' => __('Afghanistan', 'orunk-users'),
        'PL' => __('Poland', 'orunk-users'),
        'CA' => __('Canada', 'orunk-users'),
        'MA' => __('Morocco', 'orunk-users'),
        'SA' => __('Saudi Arabia', 'orunk-users'),
        'UZ' => __('Uzbekistan', 'orunk-users'),
        'PE' => __('Peru', 'orunk-users'),
        'AO' => __('Angola', 'orunk-users'),
        'MY' => __('Malaysia', 'orunk-users'),
        'MZ' => __('Mozambique', 'orunk-users'),
        'GH' => __('Ghana', 'orunk-users'),
        'YE' => __('Yemen', 'orunk-users'),
        'NP' => __('Nepal', 'orunk-users'),
        'VE' => __('Venezuela', 'orunk-users'),
        'MG' => __('Madagascar', 'orunk-users'),
        'CM' => __('Cameroon', 'orunk-users'),
        'CI' => __("Côte d'Ivoire", 'orunk-users'),
        'AU' => __('Australia', 'orunk-users'),
        'NE' => __('Niger', 'orunk-users'),
        'TW' => __('Taiwan', 'orunk-users'),
        'LK' => __('Sri Lanka', 'orunk-users'),
        'BF' => __('Burkina Faso', 'orunk-users'),
        'ML' => __('Mali', 'orunk-users'),
        'RO' => __('Romania', 'orunk-users'),
        'MW' => __('Malawi', 'orunk-users'),
        'CL' => __('Chile', 'orunk-users'),
        'KZ' => __('Kazakhstan', 'orunk-users'),
        'ZM' => __('Zambia', 'orunk-users'),
        'GT' => __('Guatemala', 'orunk-users'),
        'EC' => __('Ecuador', 'orunk-users'),
        'SY' => __('Syria', 'orunk-users'),
        'NL' => __('Netherlands', 'orunk-users'),
        'SN' => __('Senegal', 'orunk-users'),
        'KH' => __('Cambodia', 'orunk-users'),
        'TD' => __('Chad', 'orunk-users'),
        'SO' => __('Somalia', 'orunk-users'),
        'ZW' => __('Zimbabwe', 'orunk-users'),
        'GN' => __('Guinea', 'orunk-users'),
        'RW' => __('Rwanda', 'orunk-users'),
        'BJ' => __('Benin', 'orunk-users'),
        'BI' => __('Burundi', 'orunk-users'),
        'TN' => __('Tunisia', 'orunk-users'),
        'BO' => __('Bolivia', 'orunk-users'),
        'BE' => __('Belgium', 'orunk-users'),
        'HT' => __('Haiti', 'orunk-users'),
        'CU' => __('Cuba', 'orunk-users'),
        'SS' => __('South Sudan', 'orunk-users'),
        'DO' => __('Dominican Republic', 'orunk-users'),
        'CZ' => __('Czech Republic', 'orunk-users'),
        'GR' => __('Greece', 'orunk-users'),
        'JO' => __('Jordan', 'orunk-users'),
        'PT' => __('Portugal', 'orunk-users'),
        'AZ' => __('Azerbaijan', 'orunk-users'),
        'SE' => __('Sweden', 'orunk-users'),
        'HN' => __('Honduras', 'orunk-users'),
        'AE' => __('United Arab Emirates', 'orunk-users'),
        'HU' => __('Hungary', 'orunk-users'),
        'TJ' => __('Tajikistan', 'orunk-users'),
        'BY' => __('Belarus', 'orunk-users'),
        'AT' => __('Austria', 'orunk-users'),
        'PG' => __('Papua New Guinea', 'orunk-users'),
        'RS' => __('Serbia', 'orunk-users'),
        'IL' => __('Israel', 'orunk-users'),
        'CH' => __('Switzerland', 'orunk-users'),
        'TG' => __('Togo', 'orunk-users'),
        'SL' => __('Sierra Leone', 'orunk-users'),
        'LA' => __('Laos', 'orunk-users'),
        'PY' => __('Paraguay', 'orunk-users'),
    );

    // Allow other plugins/themes to modify this list if needed
    return apply_filters('orunk_countries_list', $countries);
}