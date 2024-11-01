<?php
/**
 * Plugin Name: The Crypto Cashier bridge for WooCommerce
 * Plugin URI: http://thecryptocashier.com
 * Description: Allows for alternative payment methods, including crypto-currencies/tokens.
 * Author: TheCryptoCashier
 * Version: 1.0.1
 * Text Domain: thecryptocashier-wc-bridge
 *
 * Copyright: (c) 2015-2019 TheCryptoCashier (support@thecryptocashier.com)
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   TheCryptoCashier_WC_Gateway
 * @author    TheCryptoCashier
 * @category  Admin
 * @copyright Copyright: (c) 2015-2019 TheCryptoCashier
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 */
 
defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

// Make sure The Crypto Cashier is active
if ( ! in_array( 'thecryptocashier/thecryptocashier.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

define('TOZ_WOOCOMMERCE_BRIDGE', 'INSTALLED');

/**
 * Add the gateway to Available WC Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + thecryptocashier's gateway
 */
function thecryptocashier_add_to_wc_gateways( $gateways ) {
	$gateways[] = 'TheCryptoCashier_WC_Gateway';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'thecryptocashier_add_to_wc_gateways' );


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function thecryptocashier_wc_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=thecryptocashier_gateway' ) . '">' . __( 'Configure', 'thecryptocashier-wc-bridge' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'thecryptocashier_wc_gateway_plugin_links' );


/**
 * The Crypto Cashier Payment Gateway
 *
 * Provides The Crypto Cashier Payment Gateway for WooCommerce
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		TheCryptoCashier_WC_Gateway
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		TheCryptoCashier
 */
add_action( 'plugins_loaded', 'thecryptocashier_wc_gateway_init', 11 );

function thecryptocashier_wc_gateway_init() {

	class TheCryptoCashier_WC_Gateway extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	  
			$this->id                 = 'thecryptocashier_gateway';
			//$this->icon               = apply_filters('woocommerce_tcc_icon', 'URL to image file');
			$this->has_fields         = false;
			$this->method_title       = __( 'The Crypto Cashier', 'thecryptocashier-wc-bridge' );
			$this->method_description = __( 'Allows alternative payment methods, including crypto-curriencis/tokens.', 'thecryptocashier-wc-bridge' );
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
		  
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		  
			// Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}
	
	
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_thecryptocashier_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'thecryptocashier-wc-bridge' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable The Crypto Cashier', 'thecryptocashier-wc-bridge' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Title', 'thecryptocashier-wc-bridge' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'thecryptocashier-wc-bridge' ),
					'default'     => __( 'The Crypto Cashier', 'thecryptocashier-wc-bridge' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Description', 'thecryptocashier-wc-bridge' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'thecryptocashier-wc-bridge' ),
					'default'     => __( 'Other payment options, including crypto-currencies/tokens.', 'thecryptocashier-wc-bridge' ),
					'desc_tip'    => true,
				),
				
				'instructions' => array(
					'title'       => __( 'Instructions', 'thecryptocashier-wc-bridge' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'thecryptocashier-wc-bridge' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			) );
		}
	
	
		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}
	
	
		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		
			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}
	
	
		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
            
            $order = wc_get_order( $order_id );
            if (!empty($order)) {
                $tozWcStat = tozInjectWCPaymentOptions($order->get_data(), $this->get_return_url( $order ));
                if ($tozWcStat['status'] == 'success') {
			         WC()->cart->empty_cart(); // Clear shopping cart
                }
            }
            else $tozWcStat = array('status' => 'fail', 'redirect' => '');
            
            return array(
				'result' 	=> $tozWcStat['status'],
				'redirect'	=> $tozWcStat['redirect']
			);
	
		}
	
  } // end \TheCryptoCashier_WC_Gateway class
}