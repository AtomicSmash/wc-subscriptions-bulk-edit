<?php
/**
 * Plugin Name:     Atomic Smash - WooCommerce Subscriptions Bulk Edit
 * Plugin URI:      https://www.atomicsmash.co.uk/
 * Description:     Enable bulk editing for WooCommerce Subscriptions
 * Author:          Atomic Smash
 * Author URI:      https://www.atomicsmash.co.uk/
 * Text Domain:     as
 * Version:         1.0.0
 *
 * Further documentation on WP Codex:
 * Creating custom bulk edit fields:
 *   - https://codex.wordpress.org/Plugin_API/Action_Reference/bulk_edit_custom_box
 * Saving data in custom quick edit:
 *   - https://codex.wordpress.org/Plugin_API/Action_Reference/quick_edit_custom_box#Creating_Inputs
 *
 * @package         Bulk_Editing
 */

if ( !defined( 'ABSPATH' ) ) exit; //Exit if accessed directly

if ( ! class_exists( 'AS_WC_Subscriptions_Bulk_Edit' ) ) :

    class AS_WC_Subscriptions_Bulk_Edit {

        function __construct() {
            add_action( 'init', array( $this, 'as__init_project') );
        }

        function as__init_project() {

            // Add edit option in bulk actions dropdown
            add_filter( 'bulk_actions-edit-shop_subscription', array( $this, 'as__register_custom_bulk_actions' ) );

            // Enqueue scripts and styles
            add_action( 'admin_enqueue_scripts', array( $this, 'as__enqueue_styles_and_scripts' ) );

            // Add some new input fields on the bulk edit meta box
            add_action( 'bulk_edit_custom_box', array( $this, 'as__subscription_edit_custom_box' ), 10, 2 );

            // Ajax for the saving of data in the js
            add_action( 'wp_ajax_save_bulk_edit_shop_subscription', array( $this, 'as__ajax_save_bulk_edit_shop_subscription' ) );
        }

        /**
         * Enqueue scripts and styles
         * @param  string $hook The page part we are on in Wordpress Admin
         */
        function as__enqueue_styles_and_scripts( $hook ) {

            // Register style & scripts
            if ( 'edit.php' === $hook &&
                isset( $_GET['post_type'] ) &&
                'shop_subscription' === $_GET['post_type'] ) {

                // Register
                wp_register_script( 'as__subscription_edit', plugins_url( 'assets/js/admin.js', __FILE__ ), array('jquery', 'jquery-ui-datepicker' ), null, true  );
                wp_register_style( 'as__admin_style', plugins_url( 'assets/css/admin.css' , __FILE__ ) );

                // Enqueue
                wp_enqueue_script( 'as__subscription_edit' );
                wp_enqueue_style( 'as__admin_style' );

            }
        }

        /**
         * Validate a passed in date field on the bulk edit and check whether it
         * matches the specified format
         * @param  string $date   The string that you want to check
         * @param  string $format The date format that should match $date.
         * @return bool         true if valid
         */
        public function as__validate_subscription_date( $date, $format = 'F d, Y' ) {
            $d = DateTime::createFromFormat( $format, $date );
            return $d && $d->format( $format ) == $date;
        }

        /**
         * Add a new 'Edit' dropdown item to the Subscription Table bulk actions.
         * @param  array $bulk_actions available bulk actions
         * @return array               $bulk_actions
         */
        public function as__register_custom_bulk_actions( $bulk_actions ) {
        	$bulk_actions['edit'] = __( 'Edit', 'as' );
        	return $bulk_actions;
        }

        /**
         * Add a new date picker field when bulk editing. This is only shown
         * if the post type is shop_subscription
         * @param  array $column_name
         *    available colums:
         *          - status              string      wc subscription status
         *          - order_title         string      subscription title
         *          - order_items         array       subscription order items
         *          - recurring_total     string/date - subscription price
         *          - start_date          string/date - subscription start date
         *          - trial_end_date      string/date - subscription trial end date
         *          - next_payment_date   string/date - subscription next payment date
         *          - end_date            string/date - subscription end date
         *          - orders              int - amount of orders associated with this subscription
         * @param  string $post_type   The currently edited post type in the table
         */
        public function as__subscription_edit_custom_box( $column_name, $post_type ) {

            if( $post_type !== 'shop_subscription' )
                return;

            if( ! in_array( $column_name, array( 'next_payment_date', 'recurring_total' ) ) )
                return;

            static $subscription_edit_nonce = true;
            if ( $subscription_edit_nonce ) {
                $subscription_edit_nonce = false;
                wp_nonce_field( plugin_basename( __FILE__ ), 'shop_subscription_edit_nonce' );
            }

            // Output the custom column and date picker
            ?>
            <fieldset class="bulk-edit-col-left bulk-edit-subscription">
              <div class="bulk-edit-col inline-edit-<?php echo $column_name ?>">
                <label class="bulk-edit-group">
                <?php

                switch ( $column_name ) {
                    case 'next_payment_date':
                        woocommerce_wp_text_input( array(
                            'id' => 'subscription_next_payment_date',
                            'label' => __( 'Next payment date. Make sure to set a payment in the future. Click cancel to cancel changes.' ),
                            'name' => 'next_payment_date',
                            'class' => 'short',
                            'custom_attributes' => array(
                                'readonly' => true,
                                'maxlength' => 18   // longest string date format
                            ),
                        ));
                    break;
                }
                ?>
                </label>
              </div>
            </fieldset>
            <?php
        }

        public function as__ajax_save_bulk_edit_shop_subscription() {

            if( ! isset( $_POST['action'] ) && 'save_bulk_edit_shop_subscription' != $_POST['action'] ) die();

        	// Get our variables from AJAX call
        	$post_ids          = ( ! empty( $_POST[ 'post_ids' ] ) ) ? $_POST[ 'post_ids' ] : array();
            $next_payment_date = ( ! empty( $_POST[ 'next_payment_date' ] ) ) ? $_POST[ 'next_payment_date' ] : null;

        	// if everything is in order
        	if ( ! empty( $post_ids ) && is_array( $post_ids ) ) {
        		foreach( $post_ids as $post_id ) {

                    $subscription = wcs_get_subscription( $post_id );

                    // Bail if next payment date cannot be updated
                    if( ! $subscription->can_date_be_updated( 'next_payment' ) ) continue;

                    // Get the current subscription dates
                    $current_dates = array(
                        'start' => $subscription->get_date( 'start' ),
                        'trial_end' => $subscription->get_date( 'trial_end' ),
                        'next_payment' => $subscription->get_date( 'next_payment' ),
                        'last_payment' => $subscription->get_date( 'last_payment' ),
                        'end' => $subscription->get_date( 'end' )
                    );

                    // Update Payment date
                    if( ! is_null( $next_payment_date && $this->as__validate_subscription_date( $next_payment_date ) ) ) {

                        // Create data object with the validated date
                        $date = new DateTime( $next_payment_date );

                        // Format the date as unix timestamp (capital H mean 00:00:00)
                        $current_dates['next_payment'] = $date->format('Y-m-d H:i:s');

                        // Update the next payment date meta field
                        $subscription->update_dates( $current_dates );

                    }

        		}
        	}

        	die();
        }

    }

endif;
new AS_WC_Subscriptions_Bulk_Edit;