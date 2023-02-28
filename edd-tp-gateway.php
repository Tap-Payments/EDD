<?php
/*
Plugin Name: TapPayment Gateway
Plugin URL: http://easydigitaldownloads.com/extension/tap_payment-gateway
Description: A tap_payment gateway for Easy Digital Downloads
Version: 2.0
Author: Aamir Khan
Author URI: http://pippinsplugins.com
Contributors: Aamir Khan
*/
// Don't forget to load the text domain here. TapPayment text domain is pw_edd
include('encdec_tp.php');
// registers the gateway
function pw_edd_register_gateway( $gateways ) {
	$gateways['tap_payment_gateway'] = array( 'admin_label' => 'TapPayment', 'checkout_label' => __( 'TapPayment', 'pw_edd' ) );
	return $gateways;
}
add_filter('edd_payment_gateways', 'pw_edd_register_gateway' );
// Remove this if you want a credit card form
function pw_edd_tap_payment_gateway_cc_form() {
	// register the action to remove default CC form
	return;
}
add_action('edd_tap_payment_gateway_cc_form', 'pw_edd_tap_payment_gateway_cc_form');
// add_action( 'edd_sample_gateway_cc_form', '__return_false' );
// processes the payment
function pw_edd_process_payment( $purchase_data ) {


	
	global $edd_options;
	if ($edd_options['tap_payment_return_url']) {
		$site_url = $edd_options['tap_payment_return_url'];
	}
	else {
		$site_url = site_url();
	}

	
	// check for any stored errors
	$errors = edd_get_errors();
	if ( ! $errors ) {
		$purchase_summary = edd_get_purchase_summary( $purchase_data );
		/****************************************
		* setup the payment details to be stored
		****************************************/

		$payment = array(
			'price'        => $purchase_data['price'],
			'date'         => $purchase_data['date'],
			'user_email'   => $purchase_data['user_email'],
			'purchase_key' => $purchase_data['purchase_key'],
			'currency'     => edd_get_currency(),
			'downloads'    => $purchase_data['downloads'],
			'gateway'      => 'tap_payment',
			'cart_details' => $purchase_data['cart_details'],
			'user_info'    => $purchase_data['user_info'],
			'status'       => 'pending',

		);

		$user_name = $payment['user_info']['first_name'];
		$price = $payment['price'];
		$user_email = $purchase_data['user_email'];
				
		// record the pending payment
		$order_id = edd_insert_payment( $payment );
	if ( empty( $payment ) )
	{	
		edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
	}	
        $mode = $edd_options['tap_payment_select_mode'];
		if ($mode == '1') {
	 			$active_sk = $edd_options['tap_payment_test_secret_key'];
	 		}
	 		else {
	 			$active_sk = $edd_options['tap_payment_live_secret_key'];
	 		}
		
		  $ref = $order_id; //This is a reference given by you while creating an invoice. (Details can be found in "Create a found in "Create a Payment" endpoint) 
		  $CurrencyCode = "KD"; //This is the currency of the invoice you are creating. (Details can be found in "Create a Payment" endpoint) 
		   $Total = $price; //This is the total amount the customer is asked to pay in the invoice. (Details can be found in "Create 
            $charge_url = 'https://api.tap.company/v2/charges';
			$source_id = 'src_all';
        	$trans_object["amount"]                   = $price;
        	$trans_object["currency"]                 = edd_get_currency();
        	$trans_object["threeDsecure"]             = true;
        	$trans_object["save_card"]                = false;
        	$trans_object["description"]              = $order_id;
        	$trans_object["statement_descriptor"]     = 'Sample';
        	$trans_object["metadata"]["udf1"]          = 'test';
        	$trans_object["metadata"]["udf2"]          = 'test';
        	$trans_object["reference"]["transaction"]  = 'txn_0001';
        	$trans_object["reference"]["order"]        = $order_id;
        	$trans_object["receipt"]["email"]          = false;
        	$trans_object["receipt"]["sms"]            = true;
        	$trans_object["customer"]["first_name"]    = $payment['user_info']['first_name'];
        	$trans_object["customer"]["last_name"]    = $payment['user_info']['last_name'];
        	$trans_object["customer"]["email"]        = $purchase_data['user_email'];
        	$trans_object["customer"]["phone"]["country_code"]       = '971';
        	$trans_object["customer"]["phone"]["number"] = '00000000';
        	$trans_object["source"]["id"] = $source_id;
        	$trans_object["post"]["url"] = get_permalink( $edd_options['success_page'] );
        	$trans_object["redirect"]["url"] = get_permalink( $edd_options['success_page'] );
        	$frequest = json_encode($trans_object);
        	$frequest = stripslashes($frequest);
			$curl = curl_init();
				curl_setopt_array($curl, array(
					  	CURLOPT_URL => $charge_url,
					  		CURLOPT_RETURNTRANSFER => true,
					  		CURLOPT_ENCODING => "",
					  		CURLOPT_MAXREDIRS => 10,
					  		CURLOPT_TIMEOUT => 30,
					  		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
					  		CURLOPT_CUSTOMREQUEST => "POST",
					  		CURLOPT_POSTFIELDS => $frequest,
					  		CURLOPT_HTTPHEADER => array(
					                "authorization: Bearer ".$active_sk,
					                "content-type: application/json"
					        ),
				));

			$response = curl_exec($curl);
			$err = curl_error($curl);
			$obj = json_decode($response);
			$redirct_Url = $obj->transaction->url;
		    wp_redirect($redirct_Url);
		    $fail = false;
	} else {
		$fail = true; // errors were detected
	}
	if ( $fail !== false ) {
		// if errors are present, send the user back to the purchase page so they can be corrected
		edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
	}
}
add_action( 'edd_gateway_tap_payment_gateway', 'pw_edd_process_payment' );
function edd_listen_for_tap_payment_gateway_ipn() {
	//echo "here";exit;
	if ( isset( $_GET['tap_id'] )) {
			//$listener = $_GET['edd-listener'];
			do_action( 'edd_verify_tap_payment_gateway_ipn' );
	}
}
add_action( 'init', 'edd_listen_for_tap_payment_gateway_ipn' );

function edd_process_tap_payment_gateway_ipn() {
	global $edd_options;
	$charge_url = 'https://api.tap.company/v2/charges';
	$mode = $edd_options['tap_payment_select_mode'];
			if ($mode == '1') {
	 			$active_sk = $edd_options['tap_payment_test_secret_key'];
	 		}
	 		else {
	 			$active_sk = $edd_options['tap_payment_live_secret_key'];
	 		}
			$curl = curl_init();

			curl_setopt_array($curl, array(
			  		CURLOPT_URL => "https://api.tap.company/v2/charges/".$_GET['tap_id'],
			  		CURLOPT_RETURNTRANSFER => true,
			  		CURLOPT_ENCODING => "",
			  		CURLOPT_MAXREDIRS => 10,
			  		CURLOPT_TIMEOUT => 30,
			  		CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			  		CURLOPT_CUSTOMREQUEST => "GET",
			  		CURLOPT_POSTFIELDS => "{}",
			  		CURLOPT_HTTPHEADER => array(
			    		"authorization: Bearer ".$active_sk,
			  		),
				)
			);

			$response = curl_exec($curl);
			$response = json_decode($response);
			//var_dump($response->reference->order);exit;

	if (isset($_GET['tap_id']) && $response->status == 'CAPTURED') {
		//cho "here";exit;
		 edd_insert_payment_note( $response->reference->order, '_edd_charge_id', $_GET['tap_id'] );
		edd_insert_payment_note( $response->reference->order, __( 'Transaction ID : ', 'edd-tappayments' ) . $response->reference->payment );
		
		// now we better empty the cart
		edd_empty_cart();
		
		// we change the payment status as completed
		edd_update_payment_status( $response->reference->order, 'publish' );
		edd_send_to_success_page();
	}
	else {
		edd_insert_payment_note( $response->reference->order, "Tap Payments Failed" );
        edd_insert_payment_note( $response->reference->order, '_edd_charge_id', $_GET['tap_id'] );
		// change the order as failed
		edd_update_payment_status($response->reference->order, 'failed' );

		// redirect 
		wp_redirect( get_permalink( $edd_options['failure_page'] ) );
		
	}
		
	exit;


}
add_action( 'edd_verify_tap_payment_gateway_ipn', 'edd_process_tap_payment_gateway_ipn' );
// adds the settings to the Payment Gateways section
function pw_edd_add_settings( $settings ) {
	$tap_payment_gateway_settings = array(
			'tap_payment' => array(
				'id'   => 'tap_payment',
				'name' => '<strong>' . __('Login & Pay with TapPayment Settings', 'pw_edd') . '</strong>',
				'desc' => __( 'Configure the TapPayment settings', 'pw_edd' ),
				'type' => 'header',
			),
			'test_secret_key' => array(
				'id'   => 'tap_payment_test_secret_key',
				'name' => __( 'Test Secret Key', 'pw_edd' ),
				'desc' => __( 'Test Secret Key Parameter Provided By Tap Payments', 'pw_edd' ),
				'type' => 'text',
				'size' => 'regular',
			),

			'live_secret_key' => array(
				'id'   => 'tap_payment_live_secret_key',
				'name' => __( 'Live Secret Key', 'pw_edd' ),
				'desc' => __( 'Live Secret  Key Parameter Provided By Tap Payments', 'pw_edd' ),
				'type' => 'text',
				'size' => 'regular',
			),

			'tap_payment_return_url' => array(
				'id'   => 'tap_payment_return_url',
				'name' => __( 'ReturnURL', 'pw_edd' ),
				'desc' => __( 'ReturnURL Parameter Provided By Tap Payments, this URL will be called after successful transaction,default is your site URL', 'pw_edd' ),
				'type' => 'text',
				'size' => 'regular',
			),

			'tap_payment_select_mode' => array(
				'id'   => 'tap_payment_select_mode',
				'name' => __( 'Select Mode', 'pw_edd' ),
				'desc' => __( 'Set to enable test mode', 'pw_edd' ),
				'type' => 'Checkbox',
				'size' => 'regular',
			),
		);
	return array_merge( $settings, $tap_payment_gateway_settings );
}
add_filter( 'edd_settings_gateways', 'pw_edd_add_settings' ); 