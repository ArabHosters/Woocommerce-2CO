<?php
/*
Plugin Name: 2Checkout
Plugin URI: arabhosters.com
Description: 2Checkout Payment extension for Woo-Commerece
Version: 1.0
Author: Nedal
*/

/*
 * Title   : 2Checkout Payment extension for Woo-Commerece
 * Author  : Nedal
 * Url     : arabhosters.com
 * License : arabhosters.com
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

function init_tco_gateway_class() {
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
class WC_Gateway_2CO extends WC_Payment_Gateway {

	var $notify_url;

    /**
     * Constructor for the gateway.
     *
     * @access public
     * @return void
     */
	public function __construct() {
		global $woocommerce;

        $this->id           = 'twocheckout';
        $this->icon         = WP_PLUGIN_URL . "/" . plugin_basename( dirname(__FILE__)) . '/images/credits.png';
        $this->has_fields   = false;
        $this->liveurl      = 'https://www.2checkout.com/checkout/purchase';
        $this->method_title = __( '2Checkout', 'woocommerce' );
        $this->notify_url   = str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'WC_Gateway_2CO', home_url( '/' ) ) );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title 			= $this->get_option( 'title' );
		$this->description 		= $this->get_option( 'description' );
		$this->account_number 	= $this->get_option( 'account_number' );
		$this->secret_code 	= $this->get_option( 'secret_code' );
		$this->testmode			= $this->get_option( 'testmode' );
		$this->send_shipping	= $this->get_option( 'send_shipping' );
		$this->invoice_prefix	= $this->get_option( 'invoice_prefix', 'WC-' );


		// Actions
		add_action( 'valid-twocheckout-standard-ipn-request', array( $this, 'successful_request' ) );
		add_action( 'woocommerce_receipt_twocheckout', array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_wc_gateway_2co', array( $this, 'check_ipn_response' ) );
		
		if ( !$this->is_valid_for_use() ) $this->enabled = false;

    }

    /**
     * Check if this gateway is enabled and available in the user's country
     *
     * @access public
     * @return bool
     */
    function is_valid_for_use() {
        if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_twocheckout_supported_currencies', array( 'AUD', 'BRL', 'CAD', 'MXN', 'NZD', 'HKD', 'SGD', 'USD', 'EUR', 'JPY', 'TRY', 'NOK', 'CZK', 'DKK', 'HUF', 'ILS', 'MYR', 'PHP', 'PLN', 'SEK', 'CHF', 'TWD', 'THB', 'GBP', 'RMB' ) ) ) ) return false;

        return true;
    }
	
	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {

		?>
		<h3><?php _e( '2Checkout', 'woocommerce' ); ?></h3>

    	<?php if ( $this->is_valid_for_use() ) : ?>

			<table class="form-table">
			<?php
    			// Generate the HTML For the settings form.
    			$this->generate_settings_html();
			?>
			</table><!--/.form-table-->

		<?php else : ?>
            <div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woocommerce' ); ?></strong>: <?php _e( '2Checkout does not support your store currency.', 'woocommerce' ); ?></p></div>
		<?php
			endif;
	}


    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access public
     * @return void
     */
    function init_form_fields() {

    	$this->form_fields = array(
			'enabled' => array(
							'title' => __( 'Enable/Disable', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( 'Enable 2Checkout', 'woocommerce' ),
							'default' => 'yes'
						),
			'title' => array(
							'title' => __( 'Title', 'woocommerce' ),
							'type' => 'text',
							'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
							'default' => __( '2Checkout', 'woocommerce' ),
							'desc_tip'      => true,
						),
			'description' => array(
							'title' => __( 'Description', 'woocommerce' ),
							'type' => 'textarea',
							'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
							'default' => __( 'Pay via 2Checkout; you can pay with your credit card.', 'woocommerce' )
						),
			'account_number' => array(
							'title' => __( '2Checkout account number', 'woocommerce' ),
							'type' 			=> 'text',
							'default' => ''
						),
			'secret_code' => array(
							'title' => __( '2Checkout Secret Code', 'woocommerce' ),
							'type' 			=> 'text',
							'default' => ''
						),			
			'invoice_prefix' => array(
							'title' => __( 'Invoice Prefix', 'woocommerce' ),
							'type' => 'text',
							'description' => __( 'Please enter a prefix for your invoice numbers.', 'woocommerce' ),
							'default' => 'WC-',
							'desc_tip'      => true,
						),
			'shipping' => array(
							'title' => __( 'Shipping options', 'woocommerce' ),
							'type' => 'title',
							'description' => '',
						),
			'send_shipping' => array(
							'title' => __( 'Shipping details', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( 'Send shipping details.', 'woocommerce' ),
							'default' => 'no'
						),
			'testing' => array(
							'title' => __( 'Gateway Testing', 'woocommerce' ),
							'type' => 'title',
							'description' => '',
						),
			'testmode' => array(
							'title' => __( '2Checkout sandbox', 'woocommerce' ),
							'type' => 'checkbox',
							'label' => __( 'Enable 2Checkout sandbox', 'woocommerce' ),
							'default' => 'yes',
						)
			);

    }


	/**
	 * Get 2Checkout Args for passing to PP
	 *
	 * @access public
	 * @param mixed $order
	 * @return array
	 */
	function get_tco_args( $order ) {
		global $woocommerce;

		$order_id = $order->id;

		// 2Checkout Args
		$tco_args = array(
				'sid' 					=> $this->account_number,
				'total' 				=> number_format( $order->get_total() - $order->get_shipping() - $order->get_shipping_tax() + $order->get_order_discount(), 2, '.', '' ),
				'currency_code' 		=> get_woocommerce_currency(),
				'id_type' 				=> 1,
				
				// Order key + ID
				'cart_order_id'			=> $this->invoice_prefix . ltrim( $order->get_order_number(), '#' ),
				'merchant_order_id' 	=> $order->order_key,
				//'cart_order_id' 		=> $order_id,

				// IPN
				//'notify_url'			=> $this->notify_url,
				'x_Receipt_Link_URL'	=> add_query_arg( 'utm_nooverride', '1', $this->get_return_url( $order ) ),
				
				// Billing Address info
				'first_name'			=> $order->billing_first_name,
				'last_name'				=> $order->billing_last_name,
				'street_address'		=> $order->billing_address_1,
				'street_address2'		=> $order->billing_address_2,
				'city'					=> $order->billing_city,
				'state'					=> $order->billing_state,
				'zip'					=> $order->billing_postcode,
				'country'				=> $order->billing_country,
				'email'					=> $order->billing_email,
				'phone'					=> $order->billing_phone
			);


 			$item_names = array();
			if ( sizeof( $order->get_items() ) > 0 )
				foreach ( $order->get_items() as $item )
					if ( $item['qty'] )
						$item_names[] = $item['name'] . ' x ' . $item['qty'];

			$tco_args['c_prod_1']="Order";
			$tco_args['c_name_1'] 	= sprintf( __( 'Order %s' , 'woocommerce'), $order->get_order_number() ) . " - " . implode( ', ', $item_names );
			$tco_args['c_description_1'] 		= "test desc";
			$tco_args['c_price_1'] 		= number_format( $order->get_total() - $order->get_shipping() - $order->get_shipping_tax() + $order->get_order_discount(), 2, '.', '' );

			// Shipping Cost
			if ( ( $order->get_shipping() + $order->get_shipping_tax() ) > 0 ) {
				$tco_args['c_prod_2']="Shipping";
				$tco_args['c_name_2'] = __( 'Shipping via', 'woocommerce' ) . ' ' . ucwords( $order->shipping_method_title );
				//$tco_args['quantity_2'] 	= '1';
				$tco_args['c_price_2'] 	= number_format( $order->get_shipping() + $order->get_shipping_tax() , 2, '.', '' );
			}

		$tco_args = apply_filters( 'woocommerce_tco_args', $tco_args );

		return $tco_args;
	}


    /**
	 * Generate the 2Checkout button link
     *
     * @access public
     * @param mixed $order_id
     * @return string
     */
    function generate_tco_form( $order_id ) {
		global $woocommerce;

		$order = new WC_Order( $order_id );

		if ( $this->testmode == 'yes' ):
			$tco_adr = $this->liveurl . '?demo=Y&';
		else :
			$tco_adr = $this->liveurl . '?';
		endif;

		$tco_args = $this->get_tco_args( $order );

		$tco_args_array = array();

		foreach ($tco_args as $key => $value) {
			$tco_args_array[] = '<input type="hidden" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" />';
		}

		$woocommerce->add_inline_js( '
			jQuery("body").block({
					message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to 2Checkout to make payment.', 'woocommerce' ) ) . '",
					baseZ: 99999,
					overlayCSS:
					{
						background: "#fff",
						opacity: 0.6
					},
					css: {
				        padding:        "20px",
				        zindex:         "9999999",
				        textAlign:      "center",
				        color:          "#555",
				        border:         "3px solid #aaa",
				        backgroundColor:"#fff",
				        cursor:         "wait",
				        lineHeight:		"24px",
				    }
				});
			jQuery("#submit_twocheckout_payment_form").click();
		' );

		return '<form action="'.esc_url( $tco_adr ).'" method="post" id="twocheckout_payment_form" target="_top">
				' . implode( '', $tco_args_array) . '
				<input type="submit" class="button alt" id="submit_twocheckout_payment_form" value="' . __( 'Pay via 2Checkout', 'woocommerce' ) . '" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__( 'Cancel order &amp; restore cart', 'woocommerce' ).'</a>
			</form>';

	}


    /**
     * Process the payment and return the result
     *
     * @access public
     * @param int $order_id
     * @return array
     */
	function process_payment( $order_id ) {

		$order = new WC_Order( $order_id );

			return array(
				'result' 	=> 'success',
				'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
			);

	}


    /**
     * Output for the order received page.
     *
     * @access public
     * @return void
     */
	function receipt_page( $order ) {

		echo '<p>'.__( 'Thank you for your order, please click the button below to pay with 2Checkout.', 'woocommerce' ).'</p>';

		echo $this->generate_tco_form( $order );

	}

	/**
	 * Check 2Checkout IPN validity
	 **/
	function check_ipn_request_is_valid() {
		global $woocommerce;
    	
		if($_POST['demo'] == 'Y')
        {
            $_POST['order_number'] = 1;
        }
		// this is the secret word defined in your 2Checkout account
		$string_to_hash = $this->secret_code;
		
		// this should be YOUR vendor number
		$string_to_hash .= $this->account_number;
		
		// append the order number, in this script it will always be 1
		$string_to_hash .= $_POST['order_number'];
		
		// append the sale total
		$string_to_hash .= $_POST['total'];
		
		//echo $this->secret_code;
		// get a md5 hash of the string, uppercase it to match the returned key
		$hash_to_check = strtoupper(md5($string_to_hash));
		//echo $hash_to_check;
		//echo "<br/>";
		// check to match that the key received is
		// exactly the same as the key generated
		
		//echo '<pre>';print_r($_POST);exit;
		if($_POST['key'] === $hash_to_check){
			return true;
		}

        return false;
    }


	/**
	 * Check for 2Checkout IPN Response
	 *
	 * @access public
	 * @return void
	 */
	function check_ipn_response() {

		@ob_clean();
			
    	if ( ! empty( $_POST ) && $this->check_ipn_request_is_valid() ) {

    		header( 'HTTP/1.1 200 OK' );
			
        	do_action( "valid-twocheckout-standard-ipn-request", $_POST );

		} else {

			wp_die( "2Checkout IPN Request Failure" );

   		}

	}


	/**
	 * Successful Payment!
	 *
	 * @access public
	 * @param array $posted
	 * @return void
	 */
	function successful_request( $posted ) {
		global $woocommerce;

		$posted = stripslashes_deep( $posted );		

		// Custom holds post ID
		
	    if ( ! empty( $posted['merchant_order_id'] ) && ! empty( $posted['cart_order_id'] ) ) {
	    	
		    $order = $this->get_twocheckout_order( $posted );
			
		    // Lowercase returned variables
	        $posted['credit_card_processed'] 	= strtolower( $posted['credit_card_processed'] );
			
			if($posted['credit_card_processed']=='y'){
				
				
				// Check order not already completed
	            	if ( $order->status == 'completed' ) {
	            		 exit;
	            	}

					// Validate Amount
				    if ( $order->get_total() != $posted['total'] ) {

				    	// Put this order on-hold for manual checking
				    	$order->update_status( 'on-hold', sprintf( __( 'Validation error: 2Checkout amounts do not match (gross %s).', 'woocommerce' ), $posted['total'] ) );

				    	exit;
				    }
				   

					 // Store PP Details
	                if ( ! empty( $posted['email'] ) )
	                	update_post_meta( $order->id, 'Payer 2Checkout address', $posted['email'] );
	                if ( ! empty( $posted['order_number'] ) )
	                	update_post_meta( $order->id, 'Transaction ID', $posted['order_number'] );
	                if ( ! empty( $posted['first_name'] ) )
	                	update_post_meta( $order->id, 'Payer first name', $posted['first_name'] );
	                if ( ! empty( $posted['last_name'] ) )
	                	update_post_meta( $order->id, 'Payer last name', $posted['last_name'] );
	                if ( ! empty( $posted['pay_method'] ) )
	                	update_post_meta( $order->id, 'Payment type', $posted['pay_method'] );

	                echo '<pre>';print_r($posted);exit;
	                if ( $posted['credit_card_processed'] == 'y' ) {
	                	$order->add_order_note( __( 'IPN payment completed', 'woocommerce' ) );
	                	$order->payment_complete();
	                } else {
	                	$order->update_status( 'on-hold', sprintf( __( 'Payment pending: %s', 'woocommerce' ), $posted['credit_card_processed'] ) );
	                }
			}	       

			//exit;
	    }

	}


	/**
	 * get_twocheckout_order function.
	 *
	 * @access public
	 * @param mixed $posted
	 * @return void
	 */
	function get_twocheckout_order( $posted ) {
		$custom = $posted['merchant_order_id'] ;
		//$custom = maybe_unserialize( $posted['merchant_order_id'] );

    	if ( is_numeric( $custom ) ) {
	    	$order_id = (int) $custom;
	    	$order_key = $posted['cart_order_id'];
    	} elseif( is_string( $custom ) ) {
	    	$order_id = (int) str_replace( $this->invoice_prefix, '', $custom );
	    	$order_key = $custom;
    	} else {
    		list( $order_id, $order_key ) = $custom;
		}

		$order = new WC_Order( $order_id );

		if ( ! isset( $order->id ) ) {
			// We have an invalid $order_id, probably because invoice_prefix has changed
			$order_id 	= woocommerce_get_order_id_by_order_key( $order_key );
			$order 		= new WC_Order( $order_id );
		}

		// Validate key
		if ( $order->order_key !== $order_key ) {
			echo $order->order_key."<br>".$order_key;
        	exit;
        }

        return $order;
	}

}
function add_creditcard_gateway($methods)
{
    //array_push($methods, 'WC_Gateway_2CO');
    $methods[] = 'WC_Gateway_2CO';
    return $methods;
}
add_filter('woocommerce_payment_gateways', 'add_creditcard_gateway');
}

add_action( 'plugins_loaded', 'init_tco_gateway_class' );
