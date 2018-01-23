<?php
/**
 * Load the IPN checkout-language data
 * see {@link  http://www.zen-cart.com/wiki/index.php/Developers_API_Tutorials#InitSystem wikitutorials} for more details.
 *
 */
if (!defined('IS_ADMIN_FLAG')) {
  die('Illegal Access');
}

/**
 * Require language defines
 *
 * require( 'includes/languages/english/checkout_process.php' );
 */
if( !isset( $_SESSION['language'] ) )
    $_SESSION['language'] = 'english';

$langBase = DIR_WS_LANGUAGES . $_SESSION['language'];
if( file_exists( $langBase .'/'. $template_dir_select .'checkout_process.php' ) )
    require( $langBase .'/'. $template_dir_select .'checkout_process.php' );
else
    require( $langBase .'/checkout_process.php');
?>