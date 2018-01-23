<?php
if (!defined('IS_ADMIN_FLAG'))
    die('Illegal Access');

if( MODULE_PAYMENT_FONDY_STATUS == 'True' )
{
    $za_contents[] = array(
        'text' => 'Fondy Orders',
        'link' => zen_href_link( 'fondy.php', '', 'NONSSL' )
        );
}
?>