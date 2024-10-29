<?php

/**
 * Plugin Name: Powerful Shipping Methods for WooCommerce Shipping Zones
 * Plugin URI: https://codecanyon.net/item/powerful-shipping-methods/5586711?ref=wpshowcase
 * Description: Powerful Shipping Methods is a fantastic plugin which allows you to add flexible shipping costs to shipping zones.
 * Author: WPShowCase
 * Version: 1.1
 * Author URI: http://www.codecanyon.net/user/portfolio/wpshowcase?ref=wpshowcase
 * WC tested up to: 3.3.2
 */
if ( !defined( 'ABSPATH' ) )
    exit; // Exit if accessed directly

if ( !function_exists( 'psm_value' ) ) {

    /**
     * Function for getting values from arrays
     */
    function psm_value( $array, $index, $default = '' ) {
        if ( isset( $array[ $index ] ) ) {
            return $array[ $index ];
        }
        return $default;
    }

}

//Include woocommerce
if ( !file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
    return;
}
require_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';

/**
 * This plugin is a lite version of a plugin that used to be called WooCommerce Distance Rate Shipping.
 * The text domain is not changed to keep the plugins compatible with each other.
 */
class PSM_Powerful_Shipping_Methods {

    /**
     * The constructor with actions and filters
     */
    function __construct() {
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array(
            $this, 'settings_link' ) );
        add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
    }

    /**
     * Generate language files
     */
    function plugins_loaded() {
        load_plugin_textdomain( 'powerful-shipping-methods', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Add a setting link to the plugins page
     */
    function settings_link( $links ) {
        return $links;
    }

}

$psm_powerful_shipping_methods = new PSM_Powerful_Shipping_Methods();

require_once dirname( __FILE__ ) . '/classes/shipping-method.php';
