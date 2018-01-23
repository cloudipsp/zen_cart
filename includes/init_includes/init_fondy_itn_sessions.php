<?php
/**
 * Fondy ITN specific session stuff
 *
 */
if( !defined( 'IS_ADMIN_FLAG' ) ) {
  die('Illegal Access');
}

/**
 * Begin processing. Add notice to log if logging enabled.
 */
flog(
    'ITN processing initiated. ' ."\n".
    '- Originating IP: '. $_SERVER['REMOTE_ADDR'] .' '.
    ( SESSION_IP_TO_HOST_ADDRESS == 'true' ? @gethostbyaddr( $_SERVER['REMOTE_ADDR'] ) : '' ) .
    ( $_SERVER['HTTP_USER_AGENT'] == '' ? '' : "\n" .
    '- Browser/User Agent: ' . $_SERVER['HTTP_USER_AGENT'] ) );

if( !$_POST )
{
    flog( 'ITN Fatal Error :: No POST data available -- '.
        'Most likely initiated by browser and not Fondy.' );
}

$session_post = isset( $_POST['custom_str1']) ? $_POST['custom_str1'] : '=' ;
$session_stuff = explode( '=', $session_post );
$itnFoundSession = true;
?>