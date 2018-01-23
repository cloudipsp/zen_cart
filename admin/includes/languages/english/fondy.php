<?php
/**
 * fondy.php
 *
 * Lanugage defines for the Fondy payment module within the admin console
 *
 * Copyright (c) 2008 Fondy (Pty) Ltd
 * You (being anyone who is not Fondy (Pty) Ltd) may download and use this plugin / code in your own website in conjunction with a registered and active Fondy account. If your Fondy account is terminated for any reason, you may not use this plugin / code or part thereof.
 * Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin / code or part thereof in any way.
 */

// General
define( 'TEXT_ALL', 'All' );

// Sort orders
define( 'TEXT_FONDY_SORT_ORDER_INFO', 'Display Order: ' );
define( 'TEXT_SORT_FONDY_ID_DESC', 'Fondy Order Received (new - old)' );
define( 'TEXT_SORT_FONDY_ID', 'Fondy Order Received (old - new)' );
define( 'TEXT_SORT_ZEN_ORDER_ID_DESC', 'Order ID (high - low), Fondy Order Received' );
define( 'TEXT_SORT_ZEN_ORDER_ID', 'Order ID (low - high), Fondy Order Received' );
define( 'TEXT_PAYMENT_AMOUNT_DESC', 'Order Amount (high - low)' );
define( 'TEXT_PAYMENT_AMOUNT', 'Order Amount (low - high)' );

// Page headings
define( 'HEADING_ADMIN_TITLE', 'Fondy' );
define( 'HEADING_PAYMENT_STATUS', 'Payment Status' );

// Table headings
define( 'TABLE_HEADING_ORDER_NUMBER', 'Order #' );
define( 'TABLE_HEADING_MERCHANT_REF', 'Merchant ref on Fondy' );
define( 'TABLE_HEADING_AMOUNT_GROSS', 'Gross' );
define( 'TABLE_HEADING_AMOUNT_FEE', 'Fee' );
define( 'TABLE_HEADING_AMOUNT_NET', 'Net' );
define( 'TABLE_HEADING_STATUS', 'Status' );
define( 'TABLE_HEADING_ACTION', 'Action' );

// Right pane headings
define( 'TEXT_INFO_FONDY_HEADING', 'Fondy' );
define( 'TABLE_HEADING_NUM_HISTORY_ENTRIES', 'Number of entries in Status History' );
define( 'TABLE_HEADING_ENTRY_NUM', 'Entry #' );
define( 'TABLE_HEADING_DATE_ADDED', 'Timestamp' );
define( 'TABLE_HEADING_STATUS_REASON', 'Status Reason' );

define( 'TEXT_DISPLAY_NUMBER_OF_TRANSACTIONS',
    'Displaying <strong>%d</strong> to <strong>%d</strong> (of <strong>%d</strong> entries)' );
?>