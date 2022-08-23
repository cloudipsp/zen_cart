<?php
/**
 * fondy_functions.php
 *
 * Functions used by payment module class for Fondy ITN payment method
 *
 */

// Posting URLs
define( 'MODULE_PAYMENT_FONDY_URL_LIVE', 'api.fondy.eu/api/checkout/redirect/' );
define( 'MODULE_PAYMENT_FONDY_URL_TEST', 'api.fondy.eu/api/checkout/redirect/' );

// Database tables
define( 'TABLE_FONDY', DB_PREFIX . 'fondy' );
define( 'TABLE_FONDY_SESSION', DB_PREFIX . 'fondy_session' );
define( 'TABLE_FONDY_PAYMENT_STATUS', DB_PREFIX . 'fondy_payment_status' );
define( 'TABLE_FONDY_PAYMENT_STATUS_HISTORY', DB_PREFIX . 'fondy_payment_status_history' );
define( 'TABLE_FONDY_TESTING', DB_PREFIX . 'fondy_testing' );

// Formatting
define( 'F_FORMAT_DATETIME', 'Y-m-d H:i:s' );
define( 'F_FORMAT_DATETIME_DB', 'Y-m-d H:i:s' );
define( 'F_FORMAT_DATE', 'Y-m-d' );
define( 'F_FORMAT_TIME', 'H:i' );
define( 'F_FORMAT_TIMESTAMP', 'YmdHis' );

// General
define( 'F_SESSION_LIFE', 7 );         // # of days session is saved for
define( 'F_SESSION_EXPIRE_PROB', 5 );  // Probability (%) of deleting expired sessions

function f_createUUID() {
	$uuid = sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
		mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
		mt_rand( 0, 0x0fff ) | 0x4000,
		mt_rand( 0, 0x3fff ) | 0x8000,
		mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ) );

	return ( $uuid );
}

function f_getActiveTable() {
	if ( strcasecmp( MODULE_PAYMENT_FONDY_TEST, 'Live' ) == 0 ) {
		$table = TABLE_FONDY;
	} else {
		$table = TABLE_FONDY_TESTING;
	}

	return ( $table );
}

function f_createOrderArray( $fData = null, $zcOrderId = null, $timestamp = null ) {
	// Variable initialization
	$ts = empty( $timestamp ) ? time() : $timestamp;

	$sqlArray = array(
		'm_payment_id'  => $fData['order_id'],
		'fondy_payment_id'  => $fData['payment_id'],
		'zc_order_id'   => $fData['payment_id'],
		'amount'  => $fData['amount'],
		'amount_fee'    => floatval($fData['fee']),
		'fondy_data'    => serialize( $fData ),
		'timestamp'     => date( F_FORMAT_DATETIME_DB, $ts ),
		'status'        => $fData['order_status'],
		'status_date'   => date( F_FORMAT_DATETIME_DB, $ts ),
	);
	return ( $sqlArray );
}

function f_lookupTransaction( $fData = null ) {
	// Variable initialization
	global $db;
	$data = array(
		'f_order_id'  => '',
		'zc_order_id' => '',
		'txn_type'    => '',
	);

	// Check if there is an existing order
	$sql       =
		"SELECT `id` AS `f_order_id`, `zc_order_id`, `status`
        FROM `" . f_getActiveTable() . "`
        WHERE `m_payment_id` = '" . $fData['order_id'] . "'
        LIMIT 1";
	$orderData = $db->Execute( $sql );

	$exists = ( $orderData->recordCount() > 0 );
	flog( "Record count = " . $orderData->recordCount() );

	// If record found, extract the useful information
	if ( $exists ) {
		$data = array_merge( $data, $orderData->fields );
	}

	flog( "Data:\n" . print_r( $data, true ) );

	if ( ! $exists ) {
		$data['txn_type'] = 'new';
	} elseif ( $exists && $fData['order_status'] == 'approved' ) {
		$data['txn_type'] = 'cleared';
	} elseif ( $exists && $fData['order_status'] == 'processing' ) {
		$data['txn_type'] = 'update';
	} elseif ( $exists && ( $fData['order_status'] == 'declined' or $fData['order_status'] == 'expired' ) ) {
		$data['txn_type'] = 'failed';
	} else {
		$data['txn_type'] = 'unknown';
	}

	flog( "Data to be returned:\n" . print_r( array_values( $data ), true ) );

	return ( array_values( $data ) );
}

function f_createOrderHistoryArray( $fData = null, $fOrderId = null, $timestamp = null ) {
	$sqlArray = array(
		'fondy_order_id'    => $fOrderId,
		'timestamp'     => date( F_FORMAT_DATETIME_DB, $timestamp ),
		'status'        => $fData['order_status'],
		'status_reason' => ''
	);

	return ( $sqlArray );
}

function f_updateOrderStatusAndHistory( $fData, $zcOrderId, $newStatus = 1, $txnType, $ts ) {
	// Variable initialization
	global $db;

	// Update ZenCart order table with new status
	$sql =
		"UPDATE `" . TABLE_ORDERS . "`
        SET `orders_status` = '" . (int) $newStatus . "'
        WHERE `orders_id` = '" . (int) $zcOrderId . "'";
	$db->Execute( $sql );

	// Update Fondy order with new status
	$sqlArray = array(
		'status'      => $fData['order_status'],
		'status_date' => date( F_FORMAT_DATETIME_DB, $ts ),
	);
	zen_db_perform(
		f_getActiveTable(), $sqlArray, 'update', "zc_order_id='" . $zcOrderId . "'" );

	// Create new Fondy order status history record
	$sqlArray = array(
		'orders_id'         => (int) $zcOrderId,
		'orders_status_id'  => (int) $newStatus,
		'date_added'        => date( F_FORMAT_DATETIME_DB, $ts ),
		'customer_notified' => '0',
		'comments'          => 'Fondy status: ' . $fData['order_status'],
	);
	zen_db_perform( TABLE_ORDERS_STATUS_HISTORY, $sqlArray );

	//// Activate any downloads for an order which has now cleared
	if ( $txnType == 'cleared' ) {
		$sql         =
			"SELECT `date_purchased`
            FROM `" . TABLE_ORDERS . "`
            WHERE `orders_id` = " . (int) $zcOrderId;
		$checkStatus = $db->Execute( $sql );

		$zcMaxDays = date_diff( $checkStatus->fields['date_purchased'],
				date( F_FORMAT_DATETIME ) ) + (int) DOWNLOAD_MAX_DAYS;

		flog( 'Updating order #' . (int) $zcOrderId . ' downloads. New max days: ' .
		      (int) $zcMaxDays . ', New count: ' . (int) DOWNLOAD_MAX_COUNT );

		$sql =
			"UPDATE `" . TABLE_ORDERS_PRODUCTS_DOWNLOAD . "`
            SET `download_maxdays` = " . (int) $zcMaxDays . ",
                `download_count` = " . (int) DOWNLOAD_MAX_COUNT . "
            WHERE `orders_id` = " . (int) $zcOrderId;
		$db->Execute( $sql );
	}
}

function f_removeExpiredSessions() {
	// Variable initialization
	global $db;
	$prob = mt_rand( 1, 100 );

	flog( 'Generated probability = ' . $prob
	      . ' (Expires for <= ' . F_SESSION_EXPIRE_PROB . ')' );

	if ( $prob <= F_SESSION_EXPIRE_PROB ) {
		// Removed sessions passed their expiry date
		$sql =
			"DELETE FROM `" . TABLE_FONDY_SESSION . "`
            WHERE `expiry` < '" . date( F_FORMAT_DATETIME_DB ) . "'";
		$db->Execute( $sql );
	}
}

?>