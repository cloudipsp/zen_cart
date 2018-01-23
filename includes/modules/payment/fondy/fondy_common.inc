<?php
/**
 * common.php
 *
 * Copyright (c) 2008 Fondy (Pty) Ltd
 * You (being anyone who is not Fondy (Pty) Ltd) may download and use this plugin / code in your own website in conjunction with a registered and active Fondy account. If your Fondy account is terminated for any reason, you may not use this plugin / code or part thereof.
 * Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin / code or part thereof in any way.
 * @author Jonathan Smit
 */
//// Create user agent string
// User agent constituents (for cURL)
define( 'F_SOFTWARE_NAME', 'ZenCart' );
define( 'F_SOFTWARE_VER', PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR );
define( 'F_MODULE_NAME', 'Fondy-ZenCart_1_3' );
define( 'F_MODULE_VER', '1.10' );

// Features
// - PHP
$pfFeatures = 'PHP ' . phpversion() . ';';

// - cURL
if ( in_array( 'curl', get_loaded_extensions() ) ) {
	define( 'F_CURL', '' );
	$pfVersion  = curl_version();
	$pfFeatures .= ' curl ' . $pfVersion['version'] . ';';
} else {
	$pfFeatures .= ' nocurl;';
}

// Create user agrent
define( 'F_USER_AGENT', F_SOFTWARE_NAME . '/' . F_SOFTWARE_VER . ' (' . trim( $pfFeatures ) . ') ' . F_MODULE_NAME . '/' . F_MODULE_VER );

// General Defines
define( 'F_TIMEOUT', 15 );
define( 'F_EPSILON', 0.01 );

// Messages
// Error
define( 'F_ERR_AMOUNT_MISMATCH', 'Amount mismatch' );
define( 'F_ERR_BAD_ACCESS', 'Bad access' );
define( 'F_ERR_BAD_SOURCE_IP', 'Bad source IP address' );
define( 'F_ERR_CONNECT_FAILED', 'Failed to connect to Fondy' );
define( 'F_ERR_INVALID_SIGNATURE', 'Security signature mismatch' );
define( 'F_ERR_MERCHANT_ID_MISMATCH', 'Merchant ID mismatch' );
define( 'F_ERR_NO_SESSION', 'No saved session found for ITN transaction' );
define( 'F_ERR_ORDER_ID_MISSING_URL', 'Order ID not present in URL' );
define( 'F_ERR_ORDER_ID_MISMATCH', 'Order ID mismatch' );
define( 'F_ERR_ORDER_INVALID', 'This order ID is invalid' );
define( 'F_ERR_ORDER_NUMBER_MISMATCH', 'Order Number mismatch' );
define( 'F_ERR_ORDER_PROCESSED', 'This order has already been processed' );
define( 'F_ERR_PDT_FAIL', 'PDT query failed' );
define( 'F_ERR_PDT_TOKEN_MISSING', 'PDT token not present in URL' );
define( 'F_ERR_SESSIONID_MISMATCH', 'Session ID mismatch' );
define( 'F_ERR_UNKNOWN', 'Unkown error occurred' );

// General
define( 'F_MSG_OK', 'Payment was successful' );
define( 'F_MSG_FAILED', 'Payment has failed' );
define( 'F_MSG_PENDING',
	'The payment is pending. Please note, you will receive another Instant' .
	' Transaction Notification when the payment status changes to' .
	' "Completed", or "Failed"' );


function flog( $msg = '', $close = false ) {
	static $fh = 0;
	global $module;

	// Only log if debugging is enabled
	if ( F_DEBUG ) {
		if ( $close ) {
			fclose( $fh );
		} else {
			// If file doesn't exist, create it
			if ( ! $fh ) {
				$pathinfo = pathinfo( __FILE__ );
				$fh       = fopen( $pathinfo['dirname'] . '/fondy.log', 'a+' );
			}

			// If file was successfully created
			if ( $fh ) {
				$line = date( 'Y-m-d H:i:s' ) . ' : ' . $msg . "\n";

				fwrite( $fh, $line );
			}
		}
	}
}

function fGetData() {
	// Posted variables from ITN
	if ( empty( $_POST ) ) {
		$callback = json_decode( file_get_contents( "php://input" ) );
		if ( empty( $callback ) ) {
			die( 'go away!' );
		}
		$_POST = array();
		foreach ( $callback as $key => $val ) {
			$_POST[ $key ] = $val;
		}
	}
	$fData = $_POST;

	// Strip any slashes in data
	foreach ( $fData as $key => $val ) {
		$fData[ $key ] = stripslashes( $val );
	}

	// Return "false" if no data was received
	if ( sizeof( $fData ) == 0 ) {
		return ( false );
	} else {
		return ( $fData );
	}
}

function fValidSignature( $fData = null, &$fParamString = null ) {

	$result = true;

	if ( $fParamString['merchant_id'] != $fData['merchant_id'] ) {
		$result = false;
	}
	$responseSignature = $fData['signature'];
	if ( isset( $fData['response_signature_string'] ) ) {
		unset( $fData['response_signature_string'] );
	}
	if ( isset( $fData['signature'] ) ) {
		unset( $fData['signature'] );
	}
	if ( getSignature( $fData, $fParamString['secret_key'] ) != $responseSignature ) {
		$result = false;
	}
	flog( 'Signature = ' . ( $result ? 'valid' : 'invalid' ) );

	return ( $result );
}

function getSignature( $data, $password, $encoded = true ) {
	$data = array_filter( $data, function ( $var ) {
		return $var !== '' && $var !== null;
	} );
	ksort( $data );
	$str = $password;
	foreach ( $data as $k => $v ) {
		$str .= '|' . $v;
	}
	if ( $encoded ) {
		return sha1( $str );
	} else {
		return $str;
	}
}

function fValidIP( $sourceIP ) {
	// Variable initialization
	$validHosts = array(
		'fondy.eu',
		'api.fondy.eu'
	);

	$validIps = array();

	foreach ( $validHosts as $fHostname ) {
		$ips = gethostbynamel( $fHostname );

		if ( $ips !== false ) {
			$validIps = array_merge( $validIps, $ips );
		}
	}

	// Remove duplicates
	$validIps = array_unique( $validIps );

	flog( "Valid IPs:\n" . print_r( $validIps, true ) );

	if ( in_array( $sourceIP, $validIps ) ) {
		return ( true );
	} else {
		return ( false );
	}
}

function fAmountsEqual( $amount1, $amount2 ) {

	if ( abs( floatval( $amount1 ) - floatval( $amount2 ) ) > F_EPSILON ) {
		return ( false );
	} else {
		return ( true );
	}
}
?>