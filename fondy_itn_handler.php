<?php
/**
 * fondy_itn_handler
 *
 * Callback handler for Fondy ITN
 *
 */


//// bof: Load ZenCart configuration
$show_all_errors   = false;
$current_page_base = 'fondyitn';
$loaderPrefix      = 'fondy_itn';
require_once( 'includes/configure.php' );
require_once( 'includes/application_top.php' );
require_once( 'includes/defined_paths.php' );
require_once( DIR_WS_CLASSES . 'payment.php' );

$zcSessName = '';
$zcSessID   = '';
//// eof: Load ZenCart configuration

$show_all_errors    = true;
$logdir             = defined( 'DIR_FS_LOGS' ) ? DIR_FS_LOGS : 'includes/modules/payment/fondy';
$debug_logfile_path = $logdir . '/itn_debug_php_errors-' . time() . '.log';
@ini_set( 'log_errors', 1 );
@ini_set( 'log_errors_max_len', 0 );
@ini_set( 'display_errors', 0 ); // do not output errors to screen/browser/client (only to log file)
@ini_set( 'error_log', DIR_FS_CATALOG . $debug_logfile_path );
error_reporting( version_compare( PHP_VERSION, 5.3, '>=' ) ? E_ALL & ~E_DEPRECATED & ~E_NOTICE : version_compare( PHP_VERSION, 5.4, '>=' ) ? E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_STRICT : E_ALL & ~E_NOTICE );


// Variable Initialization
$fError       = false;
$fErrMsg      = '';
$fData        = array();
$fHost        = ( strcasecmp( MODULE_PAYMENT_FONDY_TEST, 'live' ) == 0 ) ?
	MODULE_PAYMENT_FONDY_URL_LIVE : MODULE_PAYMENT_FONDY_URL_TEST;
$fOrderId     = '';
$fParamString = array(
	'merchant_id' => MODULE_PAYMENT_FONDY_MERCHANT_ID,
	'secret_key'  => MODULE_PAYMENT_FONDY_MERCHANT_KEY
);
$fDebugEmail  = defined( 'MODULE_PAYMENT_FONDY_DEBUG_EMAIL_ADDRESS' )
	? MODULE_PAYMENT_FONDY_DEBUG_EMAIL_ADDRESS : STORE_OWNER_EMAIL_ADDRESS;

flog( 'Fondy ITN call received' );

//// Notify Fondy that information has been received
if ( ! $fError ) {
	header( 'HTTP/1.0 200 OK' );
	flush();
}

//// Get data sent by Fondy
if ( ! $fError ) {
	flog( 'Get posted data' );

	// Posted variables from ITN
	$fData = fGetData();

	flog( 'Fondy Data: ' . print_r( $fData, true ) );

	if ( $fData === false ) {
		$fError  = true;
		$fErrMsg = F_ERR_BAD_ACCESS;
	}
}

//// Verify security signature
if ( ! $fError ) {
	flog( 'Verify security signature' );

	// If signature different, log for debugging
	if ( ! fValidSignature( $fData, $fParamString ) ) {
		$fError  = true;
		$fErrMsg = F_ERR_INVALID_SIGNATURE;
	}
}

//// Verify source IP (If not in debug mode)
if ( ! $fError && ! F_DEBUG ) {
	flog( 'Verify source IP' );

	if ( ! fValidIP( $_SERVER['REMOTE_ADDR'] ) ) {
		$fError  = true;
		$fErrMsg = F_ERR_BAD_SOURCE_IP;
	}
}

//// Create ZenCart order
if ( ! $fError ) {
	// Variable initialization
	$ts        = time();
	$fOrderId  = null;
	$zcOrderId = null;
	$txnType   = null;

	// Determine the transaction type
	list( $fOrderId, $zcOrderId, $txnType ) = f_lookupTransaction( $fData );

	flog( "Transaction details:" .
	      "\n- fOrderId = " . ( empty( $fOrderId ) ? 'null' : $fOrderId ) .
	      "\n- zcOrderId = " . ( empty( $zcOrderId ) ? 'null' : $zcOrderId ) .
	      "\n- txnType   = " . ( empty( $txnType ) ? 'null' : $txnType ) );
	switch ( $txnType ) {
		/**
		 * New Transaction
		 *
		 * This is for when Zen Cart sees a transaction for the first time.
		 * This doesn't necessarily mean that the transaction is in a
		 * COMPLETE state, but rather than it is new to the system
		 */
		case 'new':

			//// bof: Get Saved Session
			flog( 'Retrieving saved session' );

			// Get the Zen session name and ID from Fondy data
			list( $zcSessName, $zcSessID ) = explode( '=', $fData['product_id'] );

			flog( 'Session Name = ' . $zcSessName . ', Session ID = ' . $zcSessID );

			$sql           =
				"SELECT *
                FROM `" . TABLE_FONDY_SESSION . "`
                WHERE `session_id` = '" . $zcSessID . "'";
			$storedSession = $db->Execute( $sql );

			if ( $storedSession->recordCount() < 1 ) {
				$fError  = true;
				$fErrMsg = F_ERR_NO_SESSION;
				break;
			} else {
				$_SESSION = unserialize( base64_decode( $storedSession->fields['saved_session'] ) );
			}

			flog( 'Recreating Zen Cart order environment' );
			if ( DIR_WS_CLASSES == '' ) {
				flog( ' ***ALERT*** DIR_WS_CLASSES IS NOT DEFINED' );

			} else {
				flog( 'Additional debug information: DIR_WS_CLASSES is ' . DIR_WS_CLASSES );
			}

			if ( isset( $_SESSION ) ) {
				flog( 'SESSION IS : ' . print_r( $_SESSION, true ) );
			} else {
				flog( ' ***ALERT*** $_SESSION IS NOT DEFINED' );
			}


			// Load ZenCart shipping class
			require_once( DIR_WS_CLASSES . 'shipping.php' );
			flog( __FILE__ . ' line ' . __LINE__ );
			// Load ZenCart payment class
			require_once( DIR_WS_CLASSES . 'payment.php' );
			$payment_modules = new payment( $_SESSION['payment'] );
			flog( __FILE__ . ' line ' . __LINE__ );
			$shipping_modules = new shipping( $_SESSION['shipping'] );
			flog( __FILE__ . ' line ' . __LINE__ );
			// Load ZenCart order class
			require( DIR_WS_CLASSES . 'order.php' );
			$order = new order();
			flog( __FILE__ . ' line ' . __LINE__ );
			// Load ZenCart order_total class
			require( DIR_WS_CLASSES . 'order_total.php' );
			$order_total_modules = new order_total();
			flog( __FILE__ . ' line ' . __LINE__ );
			$order_totals = $order_total_modules->process();
			//// eof: Get ZenCart order details
			flog( __FILE__ . ' line ' . __LINE__ );
			//// bof: Check data against ZenCart order
			flog( 'Checking data against ZenCart order' );

			// Check order amount
			flog( 'Checking if amounts are the same' );

			if ( ! fAmountsEqual( $fData['amount'], $_SESSION['fondy_amount'] ) ) {
				flog( 'Amount mismatch: f amount = ' .
				      $fData['amount_gross'] . ', ZC amount = ' . $_SESSION['fondy_amount'] );

				$fError  = true;
				$fErrMsg = F_ERR_AMOUNT_MISMATCH;
				break;
			}


			// Create ZenCart order
			flog( 'Creating Zen Cart order' );
			$zcOrderId = $order->create( $order_totals );

			// Create Fondy order
			flog( 'Creating Fondy order' );

			$sqlArray = f_createOrderArray( $fData, $zcOrderId, $ts );

			zen_db_perform( TABLE_FONDY, $sqlArray );

			// Create Fondy history record

			flog( 'Creating Fondy payment status history record' );
			$fOrderId = $db->Insert_ID();

			$sqlArray = f_createOrderHistoryArray( $fData, $fOrderId, $ts );
			zen_db_perform( TABLE_FONDY_PAYMENT_STATUS_HISTORY, $sqlArray );

			// Update order status (if required)
			$newStatus = MODULE_PAYMENT_FONDY_PREPARE_ORDER_STATUS_ID;

			if ( $fData['order_status'] == 'approved' ) {

				flog( 'Setting Zen Cart order status to approve' );
				$newStatus = MODULE_PAYMENT_FONDY_ORDER_STATUS_ID;
				$sql =
					"UPDATE " . TABLE_ORDERS . "
                    SET `orders_status` = " . MODULE_PAYMENT_FONDY_ORDER_STATUS_ID . "
                    WHERE `orders_id` = '" . $zcOrderId . "'";
				$db->Execute( $sql );
			}

			// Update order status history
			flog( 'Inserting Zen Cart order status history record' );

			$sqlArray = array(
				'orders_id'         => $zcOrderId,
				'orders_status_id'  => $newStatus,
				'date_added'        => date( F_FORMAT_DATETIME_DB, $ts ),
				'customer_notified' => '0',
				'comments'          => 'Fondy id: ' . $fData['payment_id'],
			);
			zen_db_perform( TABLE_ORDERS_STATUS_HISTORY, $sqlArray );

			// Add products to order
			flog( 'Adding products to order' );

			$order->create_add_products( $zcOrderId, 2 );

			// Email customer
			flog( 'Emailing customer' );
			$order->send_order_email( $zcOrderId, 2 );

			// Empty cart
			flog( 'Emptying cart' );
			$_SESSION['cart']->reset( true );

			// Deleting stored session information
			$sql =
				"DELETE FROM `" . TABLE_FONDY_SESSION . "`
                WHERE `session_id` = '" . $zcSessID . "'";
			$db->Execute( $sql );

			// Sending email to admin
			if ( F_DEBUG ) {
				$subject = "Fondy ITN on your site";
				$body    =
					"Hi,\n\n" .
					"A Fondy transaction has been completed on your website\n" .
					"------------------------------------------------------------\n" .
					"Site: " . STORE_NAME . " (" . HTTP_SERVER . DIR_WS_CATALOG . ")\n" .
					"Order ID: " . $zcOrderId . "\n" .
					//"User ID: ". $db->f( 'user_id' ) ."\n".
					"Fondy Transaction ID: " . $fData['f_payment_id'] . "\n" .
					"Fondy Payment Status: " . $fData['payment_status'] . "\n" .
					"Order Status Code: " . $newStatus;
				zen_mail( STORE_OWNER, $fDebugEmail, $subject, $body, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, null, 'debug' );
			}

			break;

		/**
		 * Pending transaction must be cleared
		 *
		 * This is for when there is an existing order in the system which
		 * is in a PENDING state which has now been updated to COMPLETE.
		 */
		case 'cleared':

			$sqlArray = f_createOrderHistoryArray( $fData, $fOrderId, $ts );
			zen_db_perform( TABLE_FONDY_PAYMENT_STATUS_HISTORY, $sqlArray );

			$newStatus = MODULE_PAYMENT_FONDY_ORDER_STATUS_ID;
			break;

		/**
		 * Pending transaction must be updated
		 *
		 * This is when there is an existing order in the system in a PENDING
		 * state which is being updated and is STILL in a pending state.
		 *
		 * NOTE: Currently, this should never happen
		 */
		case 'update':

			$sqlArray = f_createOrderHistoryArray( $fData, $fOrderId, $ts );
			zen_db_perform( TABLE_FONDY_PAYMENT_STATUS_HISTORY, $sqlArray );

			break;

		/**
		 * Pending transaction has failed
		 *
		 * NOTE: Currently, this should never happen
		 */
		case 'failed':

			$comments = 'Payment failed (Fondy id = ' . $fData['f_payment_id'] . ')';
			$sqlArray = f_createOrderHistoryArray( $fData, $fOrderId, $ts );
			zen_db_perform( TABLE_FONDY_PAYMENT_STATUS_HISTORY, $sqlArray );

			$newStatus = MODULE_PAYMENT_FONDY_PREPARE_ORDER_STATUS_ID;

			// Sending email to admin
			$subject = "Fondy ITN Transaction on your site";
			$body    =
				"Hi,\n\n" .
				"A failed Fondy transaction on your website requires attention\n" .
				"------------------------------------------------------------\n" .
				"Site: " . STORE_NAME . " (" . HTTP_SERVER . DIR_WS_CATALOG . ")\n" .
				"Order ID: " . $zcOrderId . "\n" .
				//"User ID: ". $db->f( 'user_id' ) ."\n".
				"Fondy Transaction ID: " . $fData['f_payment_id'] . "\n" .
				"Fondy Payment Status: " . $fData['payment_status'] . "\n" .
				"Order Status Code: " . $newStatus;
			zen_mail( STORE_OWNER, $fDebugEmail, $subject, $body, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, null, 'debug' );

			break;

		/**
		 * Unknown t
		 *
		 * NOTE: Currently, this should never happen
		 */
		case 'default':
			flog( "Can not process for txn type '" . $txn_type . ":\n" .
			      print_r( $fData, true ) );
			break;
	}
}

// Update Zen Cart order and history status tables
if ( ! $fError ) {
	if ( $txnType != 'new' && ! empty( $newStatus ) ) {
		f_updateOrderStatusAndHistory( $fData, $zcOrderId, $newStatus, $txnType, $ts );
	}
}

// If an error occurred
if ( $fError ) {
	flog( 'Error occurred: ' . $fErrMsg );
	flog( 'Sending email notification' );

	$subject = "Fondy ITN error: " . $fErrMsg;
	$body    =
		"Hi,\n\n" .
		"An invalid Fondy transaction on your website requires attention\n" .
		"------------------------------------------------------------\n" .
		"Site: " . STORE_NAME . " (" . HTTP_SERVER . DIR_WS_CATALOG . ")\n" .
		"Remote IP Address: " . $_SERVER['REMOTE_ADDR'] . "\n" .
		"Remote host name: " . gethostbyaddr( $_SERVER['REMOTE_ADDR'] ) . "\n" .
		"Order ID: " . $zcOrderId . "\n";
	//"User ID: ". $db->f("user_id") ."\n";
	if ( isset( $fData['f_payment_id'] ) ) {
		$body .= "Fondy Transaction ID: " . $fData['f_payment_id'] . "\n";
	}
	if ( isset( $fData['payment_status'] ) ) {
		$body .= "Fondy Payment Status: " . $fData['payment_status'] . "\n";
	}
	$body .=
		"\nError: " . $fErrMsg . "\n";

	switch ( $fErrMsg ) {
		case f_ERR_AMOUNT_MISMATCH:
			$body .=
				"Value received : " . $fData['amount_gross'] . "\n" .
				"Value should be: " . $order->info['total'];
			break;

		// For all other errors there is no need to add additional information
		default:
			break;
	}

	zen_mail( STORE_OWNER, $fDebugEmail, $subject, $body, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, null, 'debug' );
}
// Close log
flog( '', true );
?>