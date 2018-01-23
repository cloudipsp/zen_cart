<?php
/**
 * fondy.php
 *
 * Main module file which is responsible for installing, editing and deleting
 * module details from DB and sending data to Fondy.
 */

// Load dependency files
define( 'F_DEBUG', ( MODULE_PAYMENT_FONDY_DEBUG == 'True' ? true : false ) );
include_once( (IS_ADMIN_FLAG === true ? DIR_FS_CATALOG_MODULES : DIR_WS_MODULES ) . 'payment/fondy/fondy_common.inc');
include_once( (IS_ADMIN_FLAG === true ? DIR_FS_CATALOG_MODULES : DIR_WS_MODULES ) . 'payment/fondy/fondy_functions.php');

/**
 * fondy
 *
 * Class for Fondy
 */
class fondy extends base
{
    /**
     * $code string repesenting the payment method
     * @var string
     */
    var $code;

    /**
     * $title is the displayed name for this payment method
     * @var string
     */
    var $title;

    /**
     * $description is a soft name for this payment method
     * @var string
     */
    var $description;

    /**
     * $enabled determines whether this module shows or not... in catalog.
     * @var boolean
     */
    var $enabled;

    function fondy( $fondy_ipn_id = '' )
    {
        // Variable initialization
        global $order, $messageStack;
        $this->code = 'fondy';
        $this->codeVersion = '0.0.1';

        // Set payment module title in Admin
        if( IS_ADMIN_FLAG === true )
        {
            $this->title = MODULE_PAYMENT_FONDY_TEXT_ADMIN_TITLE;

            // Check if in test mode
            if( IS_ADMIN_FLAG === true && MODULE_PAYMENT_FONDY_TEST == 'Test' )
                $this->title .= '<span class="alert"> (test mode active)</span>';
        }
        // Set payment module title in Catalog
        else
        {
            $this->title = MODULE_PAYMENT_FONDY_TEXT_CATALOG_TITLE;
        }

        // Set other payment module variables
        $this->description = MODULE_PAYMENT_FONDY_TEXT_DESCRIPTION;
        $this->sort_order = MODULE_PAYMENT_FONDY_SORT_ORDER;
        $this->enabled = ( ( MODULE_PAYMENT_FONDY_STATUS == 'True' ) ? true : false );

        if( (int)MODULE_PAYMENT_FONDY_ORDER_STATUS_ID > 0 )
            $this->order_status = MODULE_PAYMENT_FONDY_ORDER_STATUS_ID;

        if( is_object( $order ) )
            $this->update_status();

        // Set posting destination destination
        if( MODULE_PAYMENT_FONDY_TEST == 'Test' )
            $this->form_action_url = 'https://' . MODULE_PAYMENT_FONDY_URL_LIVE;
        else
            $this->form_action_url = 'https://' . MODULE_PAYMENT_FONDY_URL_TEST;

        // Check for right version
        if( PROJECT_VERSION_MAJOR != '1' && substr( PROJECT_VERSION_MINOR, 0, 3 ) != '3.9' )
            $this->enabled = false;
    }
    function update_status()
    {
        global $order, $db;

        if( ( $this->enabled == true ) && ( (int)MODULE_PAYMENT_FONDY_ZONE > 0 ) )
        {
            $check_flag = false;
            $check_query = $db->Execute(
                "SELECT `zone_id`
                FROM ". TABLE_ZONES_TO_GEO_ZONES ."
                WHERE `geo_zone_id` = '". MODULE_PAYMENT_FONDY_ZONE ."'
                  AND `zone_country_id` = '" . $order->billing['country']['id'] ."'
                ORDER BY `zone_id`");

            while( !$check_query->EOF )
            {
                if( $check_query->fields['zone_id'] < 1 )
                {
                    $check_flag = true;
                    break;
                }
                elseif( $check_query->fields['zone_id'] == $order->billing['zone_id'] )
                {
                    $check_flag = true;
                    break;
                }
                $check_query->MoveNext();
            }

            if( $check_flag == false )
            {
                $this->enabled = false;
            }
        }
    }
    function javascript_validation()
    {
        return( false );
    }
    function selection()
    {
        return array(
            'id' => $this->code,
            'module' => MODULE_PAYMENT_FONDY_TEXT_CATALOG_LOGO,
            'icon' => MODULE_PAYMENT_FONDY_TEXT_CATALOG_LOGO
            );
    }
    function pre_confirmation_check()
    {
        return( false );
    }
    function confirmation()
    {
        return( false );
    }
    function process_button()
    {
        // Variable initialization
        global $db, $order, $currencies, $currency;
        $data = array();
        $buttonArray = array();

        // Use appropriate merchant identifiers
        // Live
        if( MODULE_PAYMENT_FONDY_TEST == 'Live' )
        {
            $merchantId = MODULE_PAYMENT_FONDY_MERCHANT_ID; 
            $merchantKey = MODULE_PAYMENT_FONDY_MERCHANT_KEY;
        }
        // Sandbox
        else
        {
            $merchantId = '1396424';
            $merchantKey = 'test';
        }

        // Create URLs
        $returnUrl = zen_href_link( FILENAME_CHECKOUT_PROCESS, 'referer=fondy', 'SSL' );
        $notifyUrl = zen_href_link( 'fondy_itn_handler.php', '', 'SSL', false, false, true );

        //// Set the currency and get the order amount
        $totalsum = round($order->info['total'] * 100);
        //// Save the session (and remove expired sessions)
        f_removeExpiredSessions();
        $tsExpire = strtotime( '+'. F_SESSION_LIFE .' days' );

       
        // Delete existing record (if it exists)
        $sql =
            "DELETE FROM ". TABLE_FONDY_SESSION ."
            WHERE `session_id` = '". zen_db_input( zen_session_id() ) ."'";
        $db->Execute( $sql );

        // patch for multi-currency - AGB 19/07/13 - see also the ITN handler
        $_SESSION['fondy_amount'] = $totalsum;

        $sql =
            "INSERT INTO ". TABLE_FONDY_SESSION ."
                ( session_id, saved_session, expiry )
            VALUES (
                '". zen_db_input( zen_session_id() ) ."',
                '". base64_encode( serialize( $_SESSION ) ) ."',
                '". date( F_FORMAT_DATETIME_DB, $tsExpire ) ."' )";
        $db->Execute( $sql );

        // remove amp; before POSTing to Fondy
        $returnUrl = str_replace( "amp;", "", $returnUrl );

        //// Set the data
        $mPaymentId = f_createUUID();
        $data = array(
            // Merchant fields
            'merchant_id' => $merchantId,
            'response_url' => $returnUrl,
            'server_callback_url' => $notifyUrl,
			'currency' => $order->info['currency'],
            // Customer details
            'sender_email' => $order->customer['email_address'],
            'order_id' => $mPaymentId,
            'amount' => $totalsum,
            // Details
            'order_desc' => MODULE_PAYMENT_FONDY_PURCHASE_DESCRIPTION_TITLE . $mPaymentId,
            'product_id' => zen_session_name() .'='. zen_session_id(),
            );

        $fOutput = '';
        // Create output string
        foreach( $data as $name => $value )
        {
            $fOutput .= $name . '=' . urlencode(trim($value)) . '&';
        }
        $data['signature'] = $this->getSignature( $data, $merchantKey );
        flog( "Data to send:\n". print_r( $data, true ) );
        //// Check the data and create the process button array

        foreach( $data as $name => $value )
        {
            // Remove quotation marks
            $value = str_replace( '"', '', $value );

            $buttonArray[] = zen_draw_hidden_field( $name, $value );
        }

        $processButtonString = implode( "\n", $buttonArray ) ."\n";

        return( $processButtonString );
    }
    function before_process()
    {
        $pre = __METHOD__ .' : ';
        flog( $pre.'bof' );

        // Variable initialization
        global $db, $order_total_modules;

        // If page was called correctly with "referer" tag
        if( isset( $_GET['referer'] ) && strcasecmp( $_GET['referer'], 'fondy' ) == 0 )
        {
            $this->notify( 'NOTIFY_PAYMENT_FONDY_RETURN_TO_STORE' );

            // Reset all session variables
            $_SESSION['cart']->reset( true );
            unset( $_SESSION['sendto'] );
            unset( $_SESSION['billto'] );
            unset( $_SESSION['shipping'] );
            unset( $_SESSION['payment'] );
            unset( $_SESSION['comments'] );
            unset( $_SESSION['cot_gv'] );
            $order_total_modules->clear_posts();

            // Redirect to the checkout success page
            zen_redirect( zen_href_link( FILENAME_CHECKOUT_SUCCESS, '', 'SSL' ) );
        }
        else
        {
            $this->notify( 'NOTIFY_PAYMENT_FONDY_CANCELLED_DURING_CHECKOUT' );

            // Remove the pending Fondy transaction from the table
            if( isset( $_SESSION['f_m_payment_id'] ) )
            {
                $sql =
                    "DELETE FROM ". f_getActiveTable() ."
                    WHERE `m_payment_id` = ". $_SESSION['f_m_payment_id'] ."
                    LIMIT 1";
                $db->Execute( $sql );

                unset( $_SESSION['f_m_payment_id'] );
            }

            // Redirect to the payment page
            zen_redirect( zen_href_link( FILENAME_CHECKOUT_PAYMENT, '', 'SSL' ) );
        }
    }
    function check_referrer( $zf_domain )
    {
        return( true );
    }
    function after_process()
    {
        $pre = __METHOD__ .' : ';
        flog( $pre.'bof' );

        // Set 'order not created' flag
        $_SESSION['order_created'] = '';

        return( false );
    }
    function output_error()
    {
        return( false );
    }
    function check()
    {
        // Variable initialization
        global $db;

        if( !isset( $this->_check ) )
        {
            $check_query = $db->Execute(
                "SELECT `configuration_value`
                FROM ". TABLE_CONFIGURATION ."
                WHERE `configuration_key` = 'MODULE_PAYMENT_FONDY_STATUS'");
            $this->_check = $check_query->RecordCount();
        }

        return( $this->_check );
    }
    function install()
    {
        // Variable Initialization
        global $db;

        //// Insert configuration values
        // MODULE_PAYMENT_FONDY_STATUS (Default = False)
        $db->Execute(
            "INSERT INTO ". TABLE_CONFIGURATION ."( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added )
            VALUES( 'Enable Fondy?', 'MODULE_PAYMENT_FONDY_STATUS', 'False', 'Do you want to enable Fondy?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now() )" );
        // MODULE_PAYMENT_FONDY_MERCHANT_ID (Default = Generic sandbox credentials)
        $db->Execute(
            "INSERT INTO ". TABLE_CONFIGURATION ."( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added )
            VALUES( 'Merchant ID', 'MODULE_PAYMENT_FONDY_MERCHANT_ID', '1396424', 'Your Merchant ID from Fondy<br><span style=\"font-size: 0.9em; color: green;\">(Click <a href=\"https://portal.fondy.eu/mportal/#/merchant/list\" target=\"_blank\">here</a> to get yours.)</span>', '6', '0', now() )" );
        // MODULE_PAYMENT_FONDY_MERCHANT_KEY (Default = Generic sandbox credentials)
        $db->Execute(
            "INSERT INTO ". TABLE_CONFIGURATION ."( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added )
            VALUES( 'Merchant Key', 'MODULE_PAYMENT_FONDY_MERCHANT_KEY', 'test', 'Your Merchant Secret Key from Fondy<br><span style=\"font-size: 0.9em; color: green;\">(Click <a href=\"https://portal.fondy.eu/mportal/#/merchant/list\" target=\"_blank\">here</a> to get yours.)</span>', '6', '0', now() )" );
        // MODULE_PAYMENT_FONDY_TEST (Default = Test)
        $db->Execute(
            "INSERT INTO ". TABLE_CONFIGURATION ."( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added )
            VALUES( 'Transaction TEST', 'MODULE_PAYMENT_FONDY_TEST', 'Test', 'Select the Fondy test mode', '6', '0', 'zen_cfg_select_option(array(\'Live\', \'Test\'), ', now() )" );
        // MODULE_PAYMENT_FONDY_SORT_ORDER (Default = 0)
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added )
            VALUES( 'Sort Display Order', 'MODULE_PAYMENT_FONDY_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())" );
        // MODULE_PAYMENT_FONDY_ZONE (Default = "-none-")
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added )
            VALUES( 'Payment Zone', 'MODULE_PAYMENT_FONDY_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())" );
        // MODULE_PAYMENT_FONDY_PREPARE_ORDER_STATUS_ID
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added )
            VALUES( 'Set Preparing Order Status', 'MODULE_PAYMENT_FONDY_PREPARE_ORDER_STATUS_ID', '1', 'Set the status of prepared orders made with Fondy to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        // MODULE_PAYMENT_FONDY_ORDER_STATUS_ID
        $db->Execute(
            "INSERT INTO " . TABLE_CONFIGURATION . "( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added )
            VALUES( 'Set Acknowledged Order Status', 'MODULE_PAYMENT_FONDY_ORDER_STATUS_ID', '2', 'Set the status of orders made with Fondy to this value', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
        // MODULE_PAYMENT_FONDY_DEBUG (Default = False)
        $db->Execute(
            "INSERT INTO ". TABLE_CONFIGURATION ."( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added )
            VALUES( 'Enable debugging?', 'MODULE_PAYMENT_FONDY_DEBUG', 'False', 'Do you want to enable debugging?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now() )" );
        // MODULE_PAYMENT_FONDY_DEBUG_EMAIL
        $db->Execute(
            "INSERT INTO ". TABLE_CONFIGURATION ."( configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added )
            VALUES( 'Debug email address', 'MODULE_PAYMENT_FONDY_DEBUG_EMAIL', '', 'Where would you like debugging information emailed?', '6', '0', now() )" );

        //// Create tables
        $tables = array();
        $result = $db->Execute( "SHOW TABLES LIKE 'fondy%'" );
        $fieldName = 'Tables_in_'. DB_DATABASE .' (fondy%)';

        while( !$result->EOF )
        {
            $tables[] = $result->fields[$fieldName];
            $result->MoveNext();
        }

        // Main fondy table
        if( !in_array( TABLE_FONDY, $tables ) )
        {
            $db->Execute(
                "CREATE TABLE `". TABLE_FONDY ."` (
                  `id` INTEGER UNSIGNED NOT NULL auto_increment,
                  `m_payment_id` VARCHAR(36) NOT NULL,
                  `fondy_payment_id` VARCHAR(36) NOT NULL,
                  `zc_order_id` INTEGER UNSIGNED DEFAULT NULL,
                  `amount` DECIMAL(14,2) DEFAULT NULL,
                  `amount_fee` DECIMAL(14,2) DEFAULT NULL,
                  `fondy_data` TEXT DEFAULT NULL,
                  `timestamp` DATETIME DEFAULT NULL,
                  `status` VARCHAR(50) DEFAULT NULL,
                  `status_date` DATETIME DEFAULT NULL,
                  PRIMARY KEY( `id` ),
                  KEY `idx_m_payment_id` (`m_payment_id`),
                  KEY `idx_fondy_payment_id` (`fondy_payment_id`),
                  KEY `idx_zc_order_id` (`zc_order_id`),
                  KEY `idx_timestamp` (`timestamp`)
                  ) ENGINE=MyISAM DEFAULT CHARSET=latin1"
                );
        }

        // Payment status table
        if( !in_array( TABLE_FONDY_PAYMENT_STATUS, $tables ) )
        {
            $db->Execute(
                "CREATE TABLE `". TABLE_FONDY_PAYMENT_STATUS ."` (
                  `id` INTEGER UNSIGNED NOT NULL,
                  `name` VARCHAR(50) NOT NULL,
                  PRIMARY KEY  (`id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=latin1"
                );

            $db->Execute(
                "INSERT INTO `". TABLE_FONDY_PAYMENT_STATUS ."`
                    ( `id`,`name` )
                VALUES
                    ( 1, 'approved' ),
                    ( 2, 'processing' ),
                    ( 3, 'declined' )"
                );
        }

        // Payment status history table
        if( !in_array( TABLE_FONDY_PAYMENT_STATUS_HISTORY, $tables ) )
        {
            $db->Execute(
                "CREATE TABLE `". TABLE_FONDY_PAYMENT_STATUS_HISTORY ."`(
                  `id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
                  `fondy_order_id` INTEGER UNSIGNED NOT NULL,
                  `timestamp` DATETIME DEFAULT NULL,
                  `status` VARCHAR(50) DEFAULT NULL,
                  `status_reason` VARCHAR(255) DEFAULT NULL,
                  PRIMARY KEY( `id` ),
                  KEY `idx_fondy_order_id` (`fondy_order_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=latin1"
                );
        }

        // Session table
        if( !in_array( TABLE_FONDY_SESSION, $tables ) )
        {
            $db->Execute(
                "CREATE TABLE `". TABLE_FONDY_SESSION ."` (
                  `id` INTEGER UNSIGNED NOT NULL AUTO_INCREMENT,
                  `session_id` VARCHAR(100) NOT NULL,
                  `saved_session` MEDIUMBLOB NOT NULL,
                  `expiry` DATETIME NOT NULL,
                  PRIMARY KEY( `id` ),
                  KEY `idx_session_id` (`session_id`(36))
                ) ENGINE=MyISAM DEFAULT CHARSET=latin1"
                );
        }

        // Testing table
        if( !in_array( TABLE_FONDY_TESTING, $tables ) )
        {
            $db->Execute(
                "CREATE TABLE `". TABLE_FONDY_TESTING ."` (
                  `id` INTEGER UNSIGNED NOT NULL auto_increment,
                  `m_payment_id` VARCHAR(36) NOT NULL,
                  `fondy_payment_id` VARCHAR(36) NOT NULL,
                  `zc_order_id` INTEGER UNSIGNED DEFAULT NULL,
                  `amount` DECIMAL(14,2) DEFAULT NULL,
                  `amount_fee` DECIMAL(14,2) DEFAULT NULL,
                  `fondy_data` TEXT DEFAULT NULL,
                  `timestamp` DATETIME DEFAULT NULL,
                  `status` VARCHAR(50) DEFAULT NULL,
                  `status_date` DATETIME DEFAULT NULL,
                  PRIMARY KEY( `id` ),
                  KEY `idx_m_payment_id` (`m_payment_id`),
                  KEY `idx_fondy_payment_id` (`fondy_payment_id`),
                  KEY `idx_zc_order_id` (`zc_order_id`),
                  KEY `idx_timestamp` (`timestamp`)
                  ) ENGINE=MyISAM DEFAULT CHARSET=latin1"
                );
        }

        $this->notify( 'NOTIFY_PAYMENT_FONDY_INSTALLED' );
    }
    function remove()
    {
        // Variable Initialization
        global $db;

        // Remove all configuration variables
        $db->Execute(
            "DELETE FROM ". TABLE_CONFIGURATION ."
            WHERE `configuration_key` LIKE 'MODULE\_PAYMENT\_FONDY\_%'");

        $this->notify( 'NOTIFY_PAYMENT_FONDY_UNINSTALLED' );
    }
    function keys()
    {
        // Variable initialization
        $keys = array(
            'MODULE_PAYMENT_FONDY_STATUS',
            'MODULE_PAYMENT_FONDY_MERCHANT_ID',
            'MODULE_PAYMENT_FONDY_MERCHANT_KEY',
            'MODULE_PAYMENT_FONDY_TEST',
            'MODULE_PAYMENT_FONDY_SORT_ORDER',
            'MODULE_PAYMENT_FONDY_ZONE',
            'MODULE_PAYMENT_FONDY_PREPARE_ORDER_STATUS_ID',
            'MODULE_PAYMENT_FONDY_ORDER_STATUS_ID',
            'MODULE_PAYMENT_FONDY_DEBUG',
            'MODULE_PAYMENT_FONDY_DEBUG_EMAIL',
            );

        return( $keys );
    }
    function after_order_create( $insert_id )
    {
        $pre = __METHOD__ .' : ';
        flog( $pre.'bof' );

        return( false );
    }
	protected function fondy_filter($var)
	{
		return $var !== '' && $var !== null;
	}
	protected function getSignature($data, $password, $encoded = true)
	{
		$data = array_filter($data, array($this, 'fondy_filter'));
		ksort($data);
		$str = $password;
		foreach ($data as $k => $v) {
			$str .= '|' . $v;
		}
		if ($encoded) {
			return sha1($str);
		} else {
			return $str;
		}
	}
}
?>