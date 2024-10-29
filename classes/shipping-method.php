<?php

if ( !defined( 'ABSPATH' ) )
    exit; // Exit if accessed directly

/**
 * A simple shipping method allowing site's to set the delivery rate depending on the values of a few variables
 */

class PSM_Powerful_Shipping_Method extends WC_Shipping_Method {

    /**
     * __construct function.
     */
    function __construct( $instance_id = 0 ) {
        $this->instance_id = absint( $instance_id );
        $this->init_name();
        $this->prefix_id();
        $this->distance_rate_shipping_rates = $this->escape_array( get_option( $this->get_instance_option_key() . '_rates', array() ) );
        $this->powerful_shipping_method_settings = $this->escape_array( get_option( $this->get_instance_option_key() . '_rate_settings', array() ) );
        $this->init();
        add_filter( 'woocommerce_shipping_methods', array( $this, 'add_shipping_method' ) );
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array(
            $this, 'update_order_review' ) );
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array(
            $this, 'process_admin_options' ) );
        add_action( 'woocommerce_new_order_item', array( $this, 'woocommerce_new_order_item' ), 10, 3 );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'woocommerce_checkout_update_order_meta' ), 10, 2 );
        $this->supports = $this->supports_array();
        if ( is_admin() && !empty( $_GET[ 'tab' ] ) && $_GET[ 'tab' ] == 'shipping' ) {
            add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
        }
        $this->tax_status = esc_html( $this->get_option( 'tax_status' ) );
    }

    /**
     * Adds that this is a zone to the id of this shipping method
     */
    function prefix_id() {
        $this->id = 'zone_' . $this->id;
    }

    /**
     * Sanitizes an array
     */
    function sanitize_array( $array ) {
        if ( empty( $array ) || is_int( $array ) || is_float( $array ) ) {
            return $array;
        }
        if ( is_string( $array ) ) {
            return esc_html( $array );
        }
        foreach ( $array as $key => $value ) {
            if ( is_string( $value ) ) {
                $array[ $key ] = sanitize_text_field( $value );
            }
            if ( is_array( $value ) ) {
                $array[ $key ] = $this->sanitize_array( $value );
            }
        }
        return $array;
    }

    /**
     * Escapes an array
     */
    function escape_array( $array ) {
        if ( empty( $array ) || is_int( $array ) || is_float( $array ) ) {
            return $array;
        }
        if ( is_string( $array ) ) {
            return esc_html( $array );
        }
        foreach ( $array as $key => $value ) {
            if ( is_string( $value ) ) {
                $array[ $key ] = esc_html( $value );
            }
            if ( is_array( $value ) ) {
                $array[ $key ] = $this->escape_array( $value );
            }
        }
        return $array;
    }

    /**
     * Gets the title option
     */
    function get_title() {
        return esc_html( $this->get_option( 'title' ) );
    }

    /**
     * Inherited function
     */
    function supports_array() {
        return array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );
    }

    /**
     * Initializes variables
     */
    function init_name() {
        $this->id = 'distance_rate_shipping';
        $this->method_title = __( 'Powerful Shipping Method', 'powerful-shipping-methods' );
    }

    /**
     * init function - completes construction of object
     */
    function init() {
// Load form fields and settings
        $this->init_form_fields();
        $form_fields = $this->get_instance_form_fields();
        $this->init_settings();
// Load user variables
        $this->title = esc_html( $this->get_option( 'title' ) );
    }

    /**
     * process_rates function - Woocommerce update options shipping hook to save rates.
     */
    function process_admin_options() {
        $post_data = $this->get_post_data();
        $rule_data_serialized = psm_value( $post_data, 'distance_rates' );
        $rule_data = array();
        parse_str( $rule_data_serialized, $rule_data );
        $rule_data = $this->sanitize_array( $rule_data );
        $variables = array( 'order_total', 'weight', 'volume', 'dimensional_weight',
            'quantity', );
        if ( !empty( $rule_data ) ) {
            foreach ( $rule_data as $rule_id => $rule ) {
                if ( !empty( $rule[ 'fee' ] ) && !is_numeric( $rule[ 'fee' ] ) ) {
                    unset( $rule[ 'fee' ] );
                }
                foreach ( $variables as $variable ) {
                    if ( !empty( $rule[ 'minimum_' . $variable ] ) && !is_numeric( $rule[ 'minimum_' . $variable ] ) ) {
                        unset( $rule[ 'minimum_' . $variable ] );
                    }
                    if ( !empty( $rule[ 'maximum_' . $variable ] ) && !is_numeric( $rule[ 'minimum_' . $variable ] ) ) {
                        unset( $rule[ 'maximum_' . $variable ] );
                    }
                    if ( !empty( $rule[ 'fee_per_' . $variable ] ) && !is_numeric( $rule[ 'minimum_' . $variable ] ) ) {
                        unset( $rule[ 'fee_per_' . $variable ] );
                    }
                    if ( $rule[ 'starting_from_' . $variable ] != '0' && $rule[ 'starting_from_' . $variable ]
                            != 'minimum' ) {
                        unset( $rule[ 'starting_from_' . $variable ] );
                    }
                }
            }
        }
        $rates = psm_value( $rule_data, 'distance_rate' );
        $settings = psm_value( $rule_data, 'distance_settings', array() );
        update_option( $this->get_instance_option_key() . '_rates', $rates );
        $this->distance_rate_shipping_rates = $rates;
        update_option( $this->get_instance_option_key() . '_rate_settings', $settings );
        $this->powerful_shipping_method_settings = $settings;
        return parent::process_admin_options();
    }

    /**
     * filter that adds this class as a shipping rate.
     *
     */
    function add_shipping_method( $methods ) {
        $methods[ $this->id ] = get_class( $this );
        return $methods;
    }

    /**
     * calculates the shipping based on the user settings and the package destination
     */
    function calculate_shipping( $package = array() ) {
        $this->current_store = 'base';
        $order_total = $package[ 'contents_cost' ];
        $cost = 999999999;
        foreach ( $this->distance_rate_shipping_rates as $delivery_rate_id =>
                    $delivery_rate ) {
            $satisfies_class_conditions = false;
            $this->current_rate = $this->distance_rate_shipping_rates[ $delivery_rate_id ];
            $this->current_rate_id = $delivery_rate_id;
            $volume_and_weight = $this->calculate_volume_and_weight( $package );
            $volume = $volume_and_weight[ 'volume' ];
            $weight = $volume_and_weight[ 'weight' ];
            $quantity = $volume_and_weight[ 'quantity' ];
            $order_total = $volume_and_weight[ 'total' ];
            $dimensional_weight = 0.0;
            if ( floatval( $weight ) > 0 ) {
                $dimensional_weight = floatval( $volume ) / floatval( $weight );
            }
            if ( $this->check_condition( 'quantity', $delivery_rate, $quantity )
                    && $this->check_condition( 'volume', $delivery_rate, $volume )
                    && $this->check_condition( 'weight', $delivery_rate, $weight )
                    && $this->check_condition( 'order_total', $delivery_rate, $order_total )
                    && $this->check_condition( 'dimensional_weight', $delivery_rate, $dimensional_weight ) ) {
                $cost_for_this_rate = floatval( psm_value( $delivery_rate, 'fee', 0 ) )
                        + $this->get_cost( 'quantity', $delivery_rate, $quantity )
                        + $this->get_cost( 'volume', $delivery_rate, $volume ) + $this->get_cost( 'weight', $delivery_rate, $weight )
                        + $this->get_cost( 'order_total', $delivery_rate, $order_total )
                        + $this->get_cost( 'dimensional_weight', $delivery_rate, $dimensional_weight );
                if ( $cost_for_this_rate < $cost ) {
                    $cost = $cost_for_this_rate;
                }
            }
        }
        $store_cost = apply_filters( 'distance_calculate_shipping_from_address', $cost, $this->id, $this->current_rate_id, '', $package );
        if ( $store_cost < 99999999 && $store_cost > -99999999 ) {
            $rate_title = $this->get_title();
            if ( empty( $rate_title ) ) {
                $rate_title = $this->form_fields[ 'title' ][ 'default' ];
            }
            $rate = apply_filters( 'woocommerce_calculate_shipping_rate_' . $this->id, array(
                'id' => $this->get_rate_id(),
                'label' => $rate_title,
                'cost' => $store_cost,
                'package' => $package,
                    ), $package, $this );
            $this->add_rate( $rate );
        }
    }

    /**
     * Delete the transient when saving settings to ensure that shipping is recalculated.
     */
    function update_order_review() {
        WC_Cache_Helper::get_transient_version( 'shipping', true );
    }

    /**
     * add css and js files
     */
    function scripts() {
        wp_enqueue_script( 'powerful-shipping-methods', plugins_url() . '/powerful-shipping-methods/js/powerful-shipping-methods.js', array(
            'jquery' ), false, true );
        ob_start();
        $this->get_distance_rate_shipping_row( 'newRatenewRate', array(), $maxId, $ids );
        $localize_array[ 'new_row' ] = ob_get_clean();
        $localize_array[ 'confirmRemove' ] = __( 'Remove this rule?', 'powerful-shipping-methods' );
        $localize_array[ 'noConditions' ] = __( ' no conditions', 'powerful-shipping-methods' );
        $localize_array[ 'numeric_error' ] = __( 'Please enter a numeric value for this field.', 'powerful-shipping-methods' );
        $localize_array[ 'minimum_maximum_error' ] = __( 'The value of this field should be less than the maximum!', 'powerful-shipping-methods' );
        $localize_array[ 'correct_errors' ] = __( 'You have errors in your input. Please make corrections above.', 'powerful-shipping-methods' );
        $localize_array[ 'and' ] = __( ' and ', 'powerful-shipping-methods' );
        $localize_array[ 'isBetween' ] = __( ' is between ', 'powerful-shipping-methods' );
        $localize_array[ 'isAbove' ] = __( ' is above ', 'powerful-shipping-methods' );
        $localize_array[ 'isBelow' ] = __( ' is below ', 'powerful-shipping-methods' );
        $localize_array[ 'thenCharge' ] = __( ' then charge ', 'powerful-shipping-methods' );
        $localize_array[ 'plus' ] = __( ' plus ', 'powerful-shipping-methods' );
        $localize_array[ 'currencySymbol' ] = get_woocommerce_currency_symbol();
        $localize_array[ 'per' ] = __( ' per ', 'powerful-shipping-methods' );
        $localize_array[ 'startingFrom' ] = __( ' starting from ', 'powerful-shipping-methods' );
        $localize_array[ 'forShipping' ] = __( ' for shipping.', 'powerful-shipping-methods' );
        $localize_array[ 'kg' ] = esc_html( get_option( 'woocommerce_weight_unit' ) );
        $localize_array[ 'cubicCm' ] = __( 'cubic ', 'powerful-shipping-methods' ) . esc_html( get_option( 'woocommerce_dimension_unit' ) );
        $localize_array[ 'products' ] = __( 'products', 'powerful-shipping-methods' );
        $localize_array[ 'order_total' ] = __( 'order total', 'powerful-shipping-methods' );
        $localize_array[ 'weight' ] = __( 'weight', 'powerful-shipping-methods' );
        $localize_array[ 'volume' ] = __( 'volume', 'powerful-shipping-methods' );
        $localize_array[ 'dimensional_weight' ] = __( 'dimensional weight', 'powerful-shipping-methods' );
        $localize_array[ 'quantity' ] = __( 'quantity', 'powerful-shipping-methods' );
        $localize_array[ 'if' ] = __( 'If ', 'powerful-shipping-methods' );
        $localize_array[ 'ajaxurl' ] = admin_url( 'admin-ajax.php' );
        wp_localize_script( 'powerful-shipping-methods', 'powerful_shipping_method_settings', $localize_array );
        wp_register_style( 'powerful-shipping-methods-css', plugins_url() . '/powerful-shipping-methods/css/powerful-shipping-methods.css' );
        wp_enqueue_style( 'powerful-shipping-methods-css' );
    }

    /**
     * Init the admin form
     */
    function init_form_fields() {
        ob_start();
        $this->form_fields = array(
            'enabled' => array(
                'title' => __( 'Enable', 'powerful-shipping-methods' ),
                'type' => 'checkbox',
                'label' => __( 'Enable', 'powerful-shipping-methods' ) . ' ' . $this->method_title,
                'default' => 'no',
                'class' => 'powerful-shipping-methods-enabled',
                'description' => __( 'You should enable this shipping method if you would like customers to be able to use it.', 'powerful-shipping-methods' ),
                'desc_tip' => true,
            ),
            'tax_status' => array(
                'title' => __( 'Tax Status', 'powerful-shipping-methods' ),
                'type' => 'select',
                'default' => 'taxable',
                'options' => array(
                    'taxable' => __( 'Taxable', 'powerful-shipping-methods' ),
                    'none' => __( 'None', 'powerful-shipping-methods' ),
                ),
            ),
            'title' => array(
                'title' => __( 'Title', 'powerful-shipping-methods' ),
                'type' => 'text',
                'description' => __( 'This controls the label which the customer sees during checkout.', 'powerful-shipping-methods' ),
                'default' => __( 'Shipping', 'powerful-shipping-methods' ),
                'desc_tip' => true,
                'placeholder' => __( 'Delivery Charge', 'powerful-shipping-methods' ),
            ),
            'delivery_costs_table' => array(
                'type' => 'delivery_costs_table'
            ),
        );
        $this->form_fields[ 'enabled' ][ 'default' ] = 'yes';
        $this->instance_form_fields = $this->form_fields;
        return ob_get_clean();
    }

    /**
     * This table is saved and validated in process_admin_options
     */
    function validate_delivery_costs_table_field( $key, $value ) {
        return false;
    }

    /**
     * Create the table of delivery rates within parent table
     */
    function generate_delivery_costs_table_html() {
        ob_start();
        print '<tr style="vertical-align:top;"><th colspan="2"><h2>';
        _e( 'Shipping Rules', 'powerful-shipping-methods' );
        print '</h2></th></tr>';
        print '<tr><td colspan="2">';
        print $this->delivery_costs_table();
        print '</td>';
        print '</tr>';
        //Escapes html in other functions
        return ob_get_clean();
    }

    /**
     * Adds minimum and maximum condition to a row of a rule
     */
    function add_numeric_condition( $label, $name, $distance_id, $unit, $unit_after ) {
        $after = '';
        $before = '&nbsp;';
        if ( $unit_after )
            $after = ' ' . $unit;
        else
            $before = $unit;
        $min_value = '';
        if ( isset( $this->current_rate[ 'minimum_' . $name ] ) )
            $min_value = $this->current_rate[ 'minimum_' . $name ]; //This is escaped by escape_array in the constructor
        $max_value = '';
        if ( isset( $this->current_rate[ 'maximum_' . $name ] ) )
            $max_value = $this->current_rate[ 'maximum_' . $name ]; //This is escaped by escape_array in the constructor
        print '
	<td>' . $label . '</td>
	<td>' . $before . '<input class="numeric minimum minimum_' . $name . '" value="' . $min_value . '" type="text" class="numeric" placeholder="0" name="distance_rate[' . $distance_id . '][minimum_' . $name . ']" />' . $after . '</td>
	<td>' . $before . '<input class="numeric maximum maximum_' . $name . '" value="' . $max_value . '" type="text" class="numeric" placeholder="0" name="distance_rate[' . $distance_id . '][maximum_' . $name . ']" />' . $after . '</td>';
    }

    /**
     * Adds fee and starting from to a row of a rule
     */
    function add_numeric_cost( $label, $per, $name, $distance_id ) {
        $value = '';
        if ( isset( $this->current_rate[ 'fee_per_' . $name ] ) )
            $value = $this->current_rate[ 'fee_per_' . $name ]; //This is escaped by escape_array in the constructor
        $zero_selected = 'selected';
        $minimum_selected = '';
        if ( isset( $this->current_rate[ 'starting_from_' . $name ] ) && $this->current_rate[ 'starting_from_' . $name ]
                == 'minimum' ) {
            $zero_selected = '';
            $minimum_selected = 'selected';
        }
        print '
	<td class="rule-cost">' . get_woocommerce_currency_symbol() . '<input class="numeric fee_per_' . $name . '" value="' . $value . '" type="text" class="numeric" placeholder="0" name="distance_rate[' . $distance_id . '][fee_per_' . $name . ']" />' . __( 'per', 'powerful-shipping-methods' ) . ' ' . $per . '</td>
	<td class="rule-starting-from">' . __( ' starting from ', 'powerful-shipping-methods' ) . '
	<select class="starting_from_' . $name . '" name="distance_rate[' . $distance_id . '][starting_from_' . $name . ']">
                <option value="0" ' . $zero_selected . '>' . __( '0 ', 'powerful-shipping-methods' ) . '</option>
		<option value="minimum" ' . $minimum_selected . '>' . __( 'Minimum ', 'powerful-shipping-methods' ) . $label . '</option>
	</select>
	</td>';
    }

    /**
     * Explains the condition of the rule
     */
    function display_numeric_condition( $label, $name, $unit, $unit_after ) {
        $after = '';
        $before = '';
        if ( $unit_after )
            $after = ' ' . $unit;
        else
            $before = $unit;
        if ( (isset( $this->current_rate[ 'minimum_' . $name ] ) && $this->current_rate[ 'minimum_' . $name ]
                != '') || (isset( $this->current_rate[ 'maximum_' . $name ] ) && $this->current_rate[ 'minimum_' . $name ]
                != '') ) {
            if ( $this->first_display_condition )
                $this->first_display_condition = false;
            else
                _e( ' and ', 'powerful-shipping-methods' );
        }
        if ( isset( $this->current_rate[ 'minimum_' . $name ] ) && $this->current_rate[ 'minimum_' . $name ]
                != '' && isset( $this->current_rate[ 'maximum_' . $name ] ) && $this->current_rate[ 'maximum_' . $name ]
                != '' )
            print $label . __( ' is between ', 'powerful-shipping-methods' ) . $before . $this->current_rate[ 'minimum_' . $name ] . $after . __( ' and ', 'powerful-shipping-methods' ) . $before . $this->current_rate[ 'maximum_' . $name ] . $after;
        elseif ( isset( $this->current_rate[ 'minimum_' . $name ] ) && $this->current_rate[ 'minimum_' . $name ]
                != '' )
            print $label . __( ' is above ', 'powerful-shipping-methods' ) . $before . $this->current_rate[ 'minimum_' . $name ] . $after;
        elseif ( isset( $this->current_rate[ 'maximum_' . $name ] ) && $this->current_rate[ 'maximum_' . $name ]
                != '' )
            print $label . __( ' is below ', 'powerful-shipping-methods' ) . $before . $this->current_rate[ 'maximum_' . $name ] . $after;
    }

    /**
     * Explains the cost of the rule
     */
    function display_numeric_cost( $label, $name, $unit, $unit_after, $plural_unit ) {
        if ( isset( $this->current_rate[ 'fee_per_' . $name ] ) && $this->current_rate[ 'fee_per_' . $name ]
                != '' && $this->current_rate[ 'fee_per_' . $name ] != '0' ) {
            $after = '';
            $before = '';
            if ( $unit_after )
                $after = ' ' . $plural_unit;
            else
                $before = $plural_unit;
            if ( $this->first_display_cost )
                $this->first_display_cost = false;
            else
                _e( ' plus ', 'powerful-shipping-methods' );
            print get_woocommerce_currency_symbol() . $this->current_rate[ 'fee_per_' . $name ] . __( ' per ', 'powerful-shipping-methods' ) . $unit;
            _e( ' starting from ', 'powerful-shipping-methods' );
            print $before . $this->starting_from( $name, $this->current_rate ) . $after;
        }
    }

    /**
     * Gets the starting from value of the rule
     */
    function starting_from( $name, $rate ) {
        $starting_from = 0;
        if ( $rate[ 'starting_from_' . $name ] == 'minimum' )
            $starting_from = $rate[ 'minimum_' . $name ];
        if ( $starting_from == '' )
            $starting_from = 0;
        return $starting_from;
    }

    /**
     * Prints the table of delivery rates
     */
    function delivery_costs_table() {
        $maxId = -1;
        $ids = '';
        print '<div id="rules" class="' . $this->id . '_rules">';
        print '<input type="hidden" id="shipping-rate-id" value="' . $this->id . '" />';
        print '
		<h2>' . __( 'Shipping Rules', 'powerful-shipping-methods' ) . '</h2><p>';
        _e( 'Please click on Add New Rule to create a new shipping rule.', 'powerful-shipping-methods' );
        print '</p>';
        if ( !empty( $this->distance_rate_shipping_rates ) ) {
            foreach ( $this->distance_rate_shipping_rates as
                        $distance_rate_shipping_rates_row_id => $rate ) {
                $this->get_distance_rate_shipping_row( $distance_rate_shipping_rates_row_id, $rate, $maxId, $ids );
            }
        }
        $ids = ltrim( $ids, ',' );
        print '</div>';
        print '<a class="button add-distance-rate">' . __( 'Add New Rule', 'powerful-shipping-methods' ) . '</a>';
        print '<input type="hidden" id="delivery_ids" name="delivery_ids" value="' . $ids . '"/>';
    }

    /**
     * Returns one row of the delivery rule table using rule to get the values
     */
    function get_distance_rate_shipping_row( $distance_rate_shipping_rates_row_id, $rate, &$maxId, &$ids ) {
        if ( $distance_rate_shipping_rates_row_id >= $maxId )
            $maxId = $distance_rate_shipping_rates_row_id;
        $ids .= ',' . $distance_rate_shipping_rates_row_id;

        $this->current_rate = $rate;
        $this->current_rate_id = $distance_rate_shipping_rates_row_id;
        print '<div class="distance-row distance-row-' . $distance_rate_shipping_rates_row_id . '"><div>
        <input type="hidden" class="distance-rate-shipping-rates-row-id" value="' . $distance_rate_shipping_rates_row_id . '" />
		<a class="button remove-distance-rate right">' . __( 'Remove Rule', 'powerful-shipping-methods' ) . '</a> <br><br>
		<a class="button hide-advanced-rule-settings right">' . __( 'Hide Advanced Settings', 'powerful-shipping-methods' ) . '</a>
		<h3>' . __( 'Rule ', 'powerful-shipping-methods' ) . '<span class="rule-number"></span></h3>';
        print '<br />';
        print '<div class="row-content conditions-and-costs">';
        _e( 'If ', 'powerful-shipping-methods' );
        $this->first_display_condition = true;
        $this->display_numeric_condition( __( 'Cart Total', 'powerful-shipping-methods' ), 'order_total', get_woocommerce_currency_symbol(), false );
        $this->display_numeric_condition( __( 'Weight', 'powerful-shipping-methods' ), 'weight', esc_html( get_option( 'woocommerce_weight_unit', 'kg' ) ), true );
        $this->display_numeric_condition( __( 'Volume', 'powerful-shipping-methods' ), 'volume', esc_html( get_option( 'woocommerce_dimension_unit' ) ) . '<sup>3</sup>', true );
        $this->display_numeric_condition( __( 'Dimensional Weight', 'powerful-shipping-methods' ), 'dimensional_weight', sprintf( '%s<sup>3</sup>/%s', esc_html( get_option( 'woocommerce_dimension_unit' ) ), str_replace( 'lbs', 'lb', esc_html( get_option( 'woocommerce_weight_unit', 'kg' ) ) ) ), true );
        $this->display_numeric_condition( __( 'Quantity', 'powerful-shipping-methods' ), 'quantity', __( 'product(s)', 'powerful-shipping-methods' ), true );
        _e( ' then charge ', 'powerful-shipping-methods' );
        $this->first_display_cost = true;
        if ( isset( $rate[ 'fee' ] ) ) {
            $this->first_display_cost = false;
            print get_woocommerce_currency_symbol() . $rate[ 'fee' ];
        }
        $this->display_numeric_cost( __( 'Cart Total', 'powerful-shipping-methods' ), 'order_total', get_woocommerce_currency_symbol(), false, get_woocommerce_currency_symbol() );
        $this->display_numeric_cost( __( 'Weight', 'powerful-shipping-methods' ), 'weight', esc_html( get_option( 'woocommerce_weight_unit', 'kg' ) ), true, esc_html( get_option( 'woocommerce_weight_unit', 'kg' ) ) );
        $this->display_numeric_cost( __( 'Volume', 'powerful-shipping-methods' ), 'volume', esc_html( get_option( 'woocommerce_dimension_unit' ) ) . '<sup>3</sup>', true, esc_html( get_option( 'woocommerce_dimension_unit' ) ) . '<sup>3</sup>' );
        $this->display_numeric_cost( __( 'Dimensional Weight', 'powerful-shipping-methods' ), 'dimensional_weight', sprintf( '%s<sup>3</sup>/%s', esc_html( get_option( 'woocommerce_dimension_unit' ) ), str_replace( 'lbs', 'lb', esc_html( get_option( 'woocommerce_weight_unit', 'kg' ) ) ) ), true, sprintf( '%s<sup>3</sup>/%s', esc_html( get_option( 'woocommerce_dimension_unit' ) ), str_replace( 'lbs', 'lb', esc_html( get_option( 'woocommerce_weight_unit', 'kg' ) ) ) ) );
        $this->display_numeric_cost( __( 'Quantity', 'powerful-shipping-methods' ), 'quantity', __( 'product', 'powerful-shipping-methods' ), true, __( 'product(s)', 'powerful-shipping-methods' ) );
        _e( ' for shipping.', 'powerful-shipping-methods' );
        print '</div></div>';
        print '<div class="row-container">';
        print '<table class="shippingrows widefat">' .
                '<tr><th>' . __( 'Variable', 'powerful-shipping-methods' ) .
                '</th><th>' . __( 'Minimum', 'powerful-shipping-methods' ) .
                '</th><th>' . __( 'Maximum', 'powerful-shipping-methods' ) .
                '</th><th class="rule-cost">' . __( 'Cost', 'powerful-shipping-methods' ) .
                '</th><th class="rule-starting-from">' .
                __( 'Calculate Cost starting from', 'powerful-shipping-methods' ) .
                '</th></tr>';
        print '<tr class="rule-fee-row"><td>' . __( 'Fee', 'powerful-shipping-methods' ) . '</td><td></td><td></td><td class="rule-cost">';
        $fee = '';
        if ( isset( $rate[ 'fee' ] ) )
            $fee = $rate[ 'fee' ];
        print '<input type="hidden" class="distance-id" value="' . $distance_rate_shipping_rates_row_id . '" />';
        print get_woocommerce_currency_symbol() . '<input class="fee" value="' . $fee . '" type="text" class="fee numeric" name="distance_rate[' . $distance_rate_shipping_rates_row_id . '][fee]" placeholder="0" />';
        print '</td><td class="rule-starting-from">' . __( '(Flat fee when conditions fulfilled)', 'powerful-shipping-methods' ) . '</td>';
        print '</tr><tr>';

        print '</tr><tr>';
        $this->add_numeric_condition( __( 'Cart Total', 'powerful-shipping-methods' ), 'order_total', $distance_rate_shipping_rates_row_id, get_woocommerce_currency_symbol(), false );
        $this->add_numeric_cost( __( 'Cart Total', 'powerful-shipping-methods' ), get_woocommerce_currency_symbol(), 'order_total', $distance_rate_shipping_rates_row_id, false );
        print '</tr><tr>';
        $this->add_numeric_condition( __( 'Weight', 'powerful-shipping-methods' ), 'weight', $distance_rate_shipping_rates_row_id, esc_html( get_option( 'woocommerce_weight_unit', 'kg' ) ), true );
        $this->add_numeric_cost( __( 'Weight', 'powerful-shipping-methods' ), esc_html( get_option( 'woocommerce_weight_unit', 'kg' ) ), 'weight', $distance_rate_shipping_rates_row_id, true );
        print '</tr><tr>';
        $this->add_numeric_condition( __( 'Volume', 'powerful-shipping-methods' ), 'volume', $distance_rate_shipping_rates_row_id, esc_html( get_option( 'woocommerce_dimension_unit' ) ) . '<sup>3</sup>', true );
        $this->add_numeric_cost( __( 'Volume', 'powerful-shipping-methods' ), esc_html( get_option( 'woocommerce_dimension_unit' ) ) . '<sup>3</sup>', 'volume', $distance_rate_shipping_rates_row_id, true );
        print '</tr><tr>';
        $this->add_numeric_condition( __( 'Dimensional Weight', 'powerful-shipping-methods' ), 'dimensional_weight', $distance_rate_shipping_rates_row_id, sprintf( '%s<sup>3</sup>/%s', get_option( 'woocommerce_dimension_unit' ), str_replace( 'lbs', 'lb', get_option( 'woocommerce_weight_unit', 'kg' ) ) ), true );
        $this->add_numeric_cost( __( 'Dimensional Weight', 'powerful-shipping-methods' ), sprintf( '%s<sup>3</sup>/%s', esc_html( get_option( 'woocommerce_dimension_unit' ) ), esc_html( get_option( 'woocommerce_weight_unit', 'kg' ) ) ), 'dimensional_weight', $distance_rate_shipping_rates_row_id, true );
        print '</tr><tr>';
        $this->add_numeric_condition( __( 'Quantity', 'powerful-shipping-methods' ), 'quantity', $distance_rate_shipping_rates_row_id, __( 'product(s)', 'powerful-shipping-methods' ), true );
        $this->add_numeric_cost( __( 'Quantity', 'powerful-shipping-methods' ), __( 'product', 'powerful-shipping-methods' ), 'quantity', $distance_rate_shipping_rates_row_id, true );
        print '</tr>';
        print '</table>';
        do_action( 'woocommere_distance_rate_shipping_after_rule', $distance_rate_shipping_rates_row_id, $rate, $this );
        do_action( 'woocommere_distance_rate_shipping_after_rule_' . $this->id, $distance_rate_shipping_rates_row_id, $rate, $this );
        print '</div></div>';
    }

    /**
     * admin_options 
     */
    function admin_options() {
        print '<h3>' . $this->method_title . '</h3>
<p>';
        $description = wp_kses_post( apply_filters( 'shipping_method_description_' . $this->id, sprintf( __( 'This is a fantastic shipping method that allows you to set the rate of shipping dependent on a wide range of variables including distance. More settings are available <a href="%s">here</a>.', 'powerful-shipping-methods' ), admin_url( 'admin.php?page=wc-settings&tab=shipping&section=options' ) ) ) );
        print $description . '</p>
			<input type="hidden" id="distance-shipping-method" value="' . esc_html( $this->id ) . '" />
<table class = "form-table">';
        $this->generate_settings_html();
        print '</table>';
    }

    /**
     * is_available function - returns true if shipping is available
     */
    function is_available( $package ) {
        $is_available = false;
        $order_total = $package[ 'contents_cost' ];
        if ( !empty( $this->distance_rate_shipping_rates ) ) {
            foreach ( $this->distance_rate_shipping_rates as $delivery_rate_id =>
                        $delivery_rate ) {
                $this->current_rate = $this->distance_rate_shipping_rates[ $delivery_rate_id ];
                $this->current_rate_id = $delivery_rate_id;
                $volume_and_weight = $this->calculate_volume_and_weight( $package );
                $volume = $volume_and_weight[ 'volume' ];
                $weight = $volume_and_weight[ 'weight' ];
                $quantity = $volume_and_weight[ 'quantity' ];
                $order_total = $volume_and_weight[ 'total' ];
                $dimensional_weight = 0.0;
                if ( floatval( $weight ) > 0 ) {
                    $dimensional_weight = floatval( $volume ) / floatval( $weight );
                }
                if ( $this->check_condition( 'quantity', $delivery_rate, $quantity )
                        && $this->check_condition( 'volume', $delivery_rate, $volume )
                        && $this->check_condition( 'weight', $delivery_rate, $weight )
                        && $this->check_condition( 'order_total', $delivery_rate, $order_total )
                        && $this->check_condition( 'dimensional_weight', $delivery_rate, $dimensional_weight ) ) {
                    $is_available = true;
                }
            }
        }
        return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package );
    }

    /**
     * Checks a rule condition
     */
    function check_condition( $name, $delivery_rate, $value ) {
        if ( !empty( $delivery_rate[ 'minimum_' . $name ] ) && floatval( $value )
                <
                floatval( $delivery_rate[ 'minimum_' . $name ] ) ) {
            return false;
        }
        if ( !empty( $delivery_rate[ 'maximum_' . $name ] ) && floatval( $value )
                >
                floatval( $delivery_rate[ 'maximum_' . $name ] ) ) {
            return false;
        }
        return true;
    }

    /**
     * Gets the cost of a line of a rule
     */
    function get_cost( $name, $delivery_rate, $value ) {
        if ( !isset( $delivery_rate[ 'fee_per_' . $name ] ) || $delivery_rate[ 'fee_per_' . $name ]
                == '' ) {
            return 0;
        }
        $starting_from = $this->starting_from( $name, $delivery_rate );
        $value = $value - $starting_from;
        $rate_per = $delivery_rate[ 'fee_per_' . $name ];
        return $rate_per * $value;
    }

    /**
     * Calculates the volume and weight of the cart
     */
    function calculate_volume_and_weight( $package ) {
        $volume = 0;
        $weight = 0;
        $quantity = 0;
        $total = 0;
        foreach ( $package[ 'contents' ] as $order_line ) {
            $calculated_line = $this->calculate_line_volume_and_weight( $order_line );
            $volume = $volume + $calculated_line[ 'volume' ];
            $weight = $weight + $calculated_line[ 'weight' ];
            $quantity = $quantity + $calculated_line[ 'quantity' ];
            $total = $total + $calculated_line[ 'line_total' ];
        }
        return array( 'volume' => $volume, 'weight' => $weight, 'quantity' => $quantity,
            'total' => $total );
    }

    /**
     * Calculates the volume and weight of one line of the cart
     */
    function calculate_line_volume_and_weight( $order_line ) {
        $volume = 0;
        $weight = 0;
        $quantity = 0;
        $total = $order_line[ 'line_total' ];
        $product = $order_line[ 'data' ];
        $meta_id = $order_line[ 'product_id' ];
        if ( !empty( $order_line[ 'variation_id' ] ) ) {
            $meta_id = $order_line[ 'variation_id' ];
        }
        $quantity += floatval( $order_line[ 'quantity' ] );
        $weight += $quantity * floatval( $order_line[ 'data' ]->get_weight() );
        $volume += $quantity * floatval( $order_line[ 'data' ]->get_length() ) *
                floatval( $order_line[ 'data' ]->get_width() ) * floatval( $order_line[ 'data' ]->get_height() );
        return array( 'volume' => $volume, 'weight' => $weight, 'quantity' => $quantity,
            'line_total' => $total, );
    }

}

/** object oriented * */
$psm_powerful_shipping_method = new PSM_Powerful_Shipping_Method();
?>