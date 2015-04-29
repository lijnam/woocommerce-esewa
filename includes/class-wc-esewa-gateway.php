<?php
/**
 * eSewa Payment Gateway
 *
 * Provides a eSewa Payment Gateway.
 *
 * @class       WC_Gateway_eSewa
 * @extends     WC_Payment_Gateway
 * @category    Class
 * @author      AxisThemes
 * @since       1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_eSewa Class
 */
class WC_Gateway_eSewa extends WC_Payment_Gateway {


	/** @var boolean Whether or not logging is enabled */
	public static $log_enabled = false;

	/** @var WC_Logger Logger instance */
	public static $log = false;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'esewa';
		$this->icon               = apply_filters( 'woocommerce_esewa_icon', plugins_url( 'images/esewa.png', plugin_dir_path( __FILE__ ) ) );
		$this->has_fields         = false;
		$this->order_button_text  = __( 'Proceed to eSewa', 'woocommerce-esewa' );
		$this->method_title       = __( 'eSewa', 'woocommerce-esewa' );
		$this->method_description = __( 'The eSewa ePay system enables partner merchant to perform transaction and receive money from customer having eSewa account in secure environment.', 'woocommerce-esewa' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->testmode       = 'yes' === $this->get_option( 'testmode', 'no' );
		$this->debug          = 'yes' === $this->get_option( 'debug', 'no' );
		$this->merchant       = $this->get_option( 'merchant' );

		self::$log_enabled    = $this->debug;

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		if ( ! $this->is_valid_for_use() ) {
			$this->enabled = 'no';
		}
	}

	/**
	 * Logging method
	 * @param string $message
	 */
	public static function log( $message ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'esewa', $message );
		}
	}

	/**
	 * Check if this gateway is enabled and available in the user's country
	 *
	 * @return bool
	 */
	public function is_valid_for_use() {
		return in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_esewa_supported_currencies', array( 'NPR' ) ) );
	}

	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {
		if ( $this->is_valid_for_use() ) {
			parent::admin_options();
		} else {
			?>
			<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woocommerce-esewa' ); ?></strong>: <?php _e( 'eSewa does not support your store currency.', 'woocommerce-esewa' ); ?></p></div>
			<?php
		}
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = include( 'includes/settings-esewa.php' );
	}

	/**
	 * Get the transaction URL.
	 *
	 * @param  WC_Order $order
	 *
	 * @return string
	 */
	public function get_transaction_url( $order ) {
		if ( $this->testmode ) {
			$this->view_transaction_url = 'https://dev.esewa.com.np/epay/main?id=%s';
		} else {
			$this->view_transaction_url = 'http://esewa.com.np/epay/main?id=%s';
		}
		return parent::get_transaction_url( $order );
	}

	/**
	 * Process the payment and return the result
	 *
	 * @param  int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		include_once( 'includes/class-wc-gateway-esewa-request.php' );

		$order         = wc_get_order( $order_id );
		$esewa_request = new WC_Gateway_eSewa_Request( $this );

		if ( 'hosted' == $this->mode ) {
			return $this->process_hosted_payments( $order );
		} else {
			return array(
				'result'   => 'success',
				'redirect' => $esewa_request->get_request_url( $order, $this->testmode )
			);
		}
	}

	/**
	 * Process hosted payments
	 *
	 * @param  WC_Order $order
	 * @return array
	 */
	protected function process_hosted_payments( $order ) {
		return array(
			'result'   => 'success',
			'redirect' => $order->get_checkout_payment_url( true )
		);
	}
}
