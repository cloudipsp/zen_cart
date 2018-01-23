<?php
/**
 * fondy.php
 *
 * Admin module for querying payments (and associated orders) made using the
 * Fondy payment module.
 *
 */

// Max results to show per page
define( 'MAX_DISPLAY_SEARCH_RESULTS_FONDY', 10 );
define( 'FILENAME_FONDY', 'fondy.php' );

// Include ZenCart header
require('includes/application_top.php');

// Create sort order array
$fondySortOrderArray = array(
    array( 'id' => '0', 'text' => TEXT_SORT_FONDY_ID_DESC ),
    array( 'id' => '1', 'text' => TEXT_SORT_FONDY_ID ),
    array( 'id' => '2', 'text' => TEXT_SORT_ZEN_ORDER_ID_DESC ),
    array( 'id' => '3', 'text'=> TEXT_SORT_ZEN_ORDER_ID ),
    array( 'id' => '4', 'text'=> TEXT_PAYMENT_AMOUNT_DESC ),
    array( 'id' => '5', 'text'=> TEXT_PAYMENT_AMOUNT )
    );

// Set sort order
$selectedSortOrder =
    isset( $_GET['f_sort_order'] ) ? $_GET['f_sort_order'] : 0;

// Create 'order by' statement based on sort order
switch( $selectedSortOrder )
{
    case 0:  $sqlOrderBy = " ORDER BY p.`id` DESC"; break;
    case 1:  $sqlOrderBy = " ORDER BY p.`id`"; break;
    case 2:  $sqlOrderBy = " ORDER BY p.`zc_order_id` DESC, p.id"; break;
    case 3:  $sqlOrderBy = " ORDER BY p.`zc_order_id`, p.id"; break;
    case 4:  $sqlOrderBy = " ORDER BY p.`amount_gross` DESC"; break;
    case 5:  $sqlOrderBy = " ORDER BY p.`amount_gross`"; break;
    default: $sqlOrderBy = " ORDER BY p.`id` DESC"; break;
}

$action = isset( $_GET['action'] ) ? $_GET['action'] : '';
$selectedStatus = isset( $_GET['f_status'] ) ? $_GET['f_status'] : '';

require( DIR_FS_CATALOG_MODULES .'payment/fondy.php' );

// Create payment statuses array
$sql =
    "SELECT `name`
    FROM ". TABLE_FONDY_PAYMENT_STATUS ;
$result = $db->Execute( $sql );

$paymentStatuses = array();
while( !$result->EOF )
{
    $paymentStatuses[] = array(
        'id' => $result->fields['name'],
        'text' => $result->fields['name']
        );
    $result->MoveNext();
}
?>

<!doctype html public "-//W3C//DTD HTML 4.01 Transitional//EN">
<html <?php echo HTML_PARAMS; ?>>
    <head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php echo CHARSET; ?>">
    <title><?php echo TITLE; ?></title>
    <link rel="stylesheet" type="text/css" href="includes/stylesheet.css">
    <link rel="stylesheet" type="text/css" href="includes/cssjsmenuhover.css" media="all" id="hoverJS">
    <script language="javascript" src="includes/menu.js"></script>
    <script language="javascript" src="includes/general.js"></script>
    <script type="text/javascript">
    <!--
    function init()
    {
    cssjsmenu('navbar');
    if (document.getElementById)
    {
      var kill = document.getElementById('hoverJS');
      kill.disabled = true;
    }
    }
    // -->
    </script>
</head>
<body marginwidth="0" marginheight="0" topmargin="0" bottommargin="0" leftmargin="0" rightmargin="0" bgcolor="#FFFFFF" onLoad="SetFocus(), init();">

<!-- header //-->
<?php require( DIR_WS_INCLUDES . 'header.php' ); ?>
<!-- header_eof //-->

<!-- body //-->
<table border="0" width="100%" cellspacing="2" cellpadding="2">
<tr>
<!-- body_text //-->
    <td width="100%" valign="top">

        <table border="0" width="100%" cellspacing="0" cellpadding="2">
        <tr>
            <td>

                <table border="0" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <td class="pageHeading"><?php echo HEADING_ADMIN_TITLE; ?></td>
                    <td class="pageHeading" align="right"><?php echo zen_draw_separator('pixel_trans.gif', HEADING_IMAGE_WIDTH, HEADING_IMAGE_HEIGHT); ?></td>
                    <td class="smallText" align="right">
<?php
echo
    zen_draw_form( 'f_status', FILENAME_FONDY, '', 'get' ) .
    HEADING_PAYMENT_STATUS . ' ' .
    zen_draw_pull_down_menu( 'f_status',
        array_merge( array( array( 'id' => '', 'text' => TEXT_ALL ) ), $paymentStatuses ),
        $selectedStatus, 'onChange="this.form.submit();"' ) .
    zen_hide_session_id() .
    zen_draw_hidden_field( 'f_sort_order', $_GET['f_sort_order'] ) .
    '</form>';

echo
    '&nbsp;&nbsp;&nbsp;' . TEXT_FONDY_SORT_ORDER_INFO .
    zen_draw_form( 'f_sort_order', FILENAME_FONDY, '', 'get' ) . '&nbsp;&nbsp;' .
    zen_draw_pull_down_menu( 'f_sort_order', $fondySortOrderArray,
        $resetFondySortOrder, 'onChange="this.form.submit();"') .
    zen_hide_session_id() .
    zen_draw_hidden_field( 'f_status', $_GET['f_status'] ) .
    '</form>';
?>
                    </td>
                    <td class="pageHeading" align="right">
                        <?php echo zen_draw_separator( 'pixel_trans.gif', HEADING_IMAGE_WIDTH, HEADING_IMAGE_HEIGHT ); ?></td>
                </tr>
                </table>

            </td>
        </tr>
        <tr>
            <td>

            <table border="0" width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td valign="top">

                <table border="0" width="100%" cellspacing="0" cellpadding="2">
                <tr class="dataTableHeadingRow">
                    <td class="dataTableHeadingContent" nowrap>
                        <?php echo TABLE_HEADING_ORDER_NUMBER; ?></td>
                    <td class="dataTableHeadingContent" nowrap>
                        <?php echo TABLE_HEADING_MERCHANT_REF; ?></td>
                    <td class="dataTableHeadingContent" nowrap>
                        <?php echo TABLE_HEADING_STATUS; ?></td>
                    <td class="dataTableHeadingContent" align="right" nowrap>
                        <?php echo TABLE_HEADING_AMOUNT_GROSS; ?></td>
                    <td class="dataTableHeadingContent" align="right" nowrap>
                        <?php echo TABLE_HEADING_AMOUNT_FEE; ?></td>
                    <td class="dataTableHeadingContent" align="right" nowrap>
                        <?php echo TABLE_HEADING_AMOUNT_NET; ?></td>
                    <td class="dataTableHeadingContent" align="right" nowrap>
                        <?php echo TABLE_HEADING_ACTION; ?>&nbsp;</td>
                </tr>
<?php
if( zen_not_null( $selectedStatus ) )
{
    $sqlSearch = " AND p.status = '". zen_db_prepare_input( $selectedStatus ) ."'";

    switch( $selectedStatus )
    {
        case 'Pending':
        case 'Completed':
        default:
            $sql =
                "SELECT p.*
                FROM ". TABLE_FONDY ." AS p, ". TABLE_ORDERS ." AS o
                WHERE o.`orders_id` = p.`zc_order_id`".
                $sqlSearch .
                $sqlOrderBy;
            break;
    }
}
else
{
    $sql =
        "SELECT p.*
        FROM `". TABLE_FONDY ."` AS p
          LEFT JOIN `". TABLE_ORDERS ."` AS o ON o.`orders_id` = p.`zc_order_id`" .
        $sqlOrderBy;
}

$split = new splitPageResults( $_GET['page'],
    MAX_DISPLAY_SEARCH_RESULTS_FONDY, $sql, $qryNumRows );
$trans = $db->Execute( $sql );

while( !$trans->EOF )
{
    $out = '';

    if( ( !isset( $_GET['f_order_id'] ) ||
          ( isset( $_GET['f_order_id'] ) && ( $_GET['f_order_id'] == $trans->fields['id'] ) ) ) &&
        !isset( $info ) )
    {
        $info = new objectInfo( $trans->fields );
    }

    //
    if( isset( $info ) && is_object( $info ) && ( $trans->fields['id'] == $info->id ) )
    {
        $out .=
            '              '.
            '<tr id="defaultSelected" class="dataTableRowSelected"'.
            ' onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)"'.
            ' onclick="document.location.href=\'' .
            zen_href_link( FILENAME_ORDERS, 'page='. $_GET['page'] .
                '&f_order_id=' . $info->id .
                '&oID=' . $info->zc_order_id .
                '&action=edit' .
                ( zen_not_null( $selectedStatus ) ? '&f_status='. $selectedStatus : '' ) .
                ( zen_not_null( $selectedSortOrder ) ? '&f_sort_order='. $selectedSortOrder : '' ) ) .
            '\'">' . "\n";
    }
    else
    {
        $out .=
            '              '.
            '<tr class="dataTableRow" onmouseover="rowOverEffect(this)"'.
            ' onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' .
            zen_href_link( FILENAME_FONDY, 'page='. $_GET['page'] .
                '&f_order_id=' . $trans->fields['id'] .
                ( zen_not_null( $selectedStatus ) ? '&f_status='. $selectedStatus : '') .
                ( zen_not_null( $selectedSortOrder ) ? '&f_sort_order='. $selectedSortOrder : '' ) ) .
            '\'">' . "\n";
    }

    $out .=
        // ZenCart order id
        '<td class="dataTableContent">'. $trans->fields['zc_order_id'] .'</td>'.

        // Fondy m_payment_id
        '<td class="dataTableContent">'. $trans->fields['m_payment_id'] .'</td>'.

        '<td class="dataTableContent">'.
        $trans->fields['status'] .'</td>'.

        // Amount Gross
        '<td class="dataTableContent" align="right">'.
        number_format( $trans->fields['amount_gross'], 2 ) .'</td>'.

        // Amount Fee
        '<td class="dataTableContent" align="right">'.
        number_format( $trans->fields['amount_fee'], 2 ) .'</td>'.

        // Amount Net
        '<td class="dataTableContent" align="right">'.
        number_format( $trans->fields['amount_net'], 2 ) .'</td>'.

        '<td class="dataTableContent" align="right">';

    if( isset( $info ) && is_object( $info ) && ( $trans->fields['id'] == $info->id ) )
        $out .= zen_image( DIR_WS_IMAGES .'icon_arrow_right.gif' );
    else
        $out .=
            '<a href="'.
            zen_href_link( FILENAME_FONDY, 'page=' . $_GET['page'] .
                '&ipnID=' . $trans->fields['paypal_ipn_id']) .
                ( zen_not_null( $selectedStatus ) ? '&f_status=' . $selectedStatus : '') .
                ( zen_not_null( $selectedSortOrder ) ? '&f_sort_order='. $selectedSortOrder : '' ) .
            '">'.
            zen_image( DIR_WS_IMAGES .'icon_info.gif', IMAGE_ICON_INFO ) .'</a>';

    $out .= '</td></tr>';

    echo $out;

    $trans->MoveNext();
}
?>
                <tr>
                    <td colspan="5">
                        <table border="0" width="100%" cellspacing="0" cellpadding="2">
                        <tr>
                            <td class="smallText" valign="top">
                                <?php echo $split->display_count( $qryNumRows,
                                    MAX_DISPLAY_SEARCH_RESULTS_FONDY, $_GET['page'],
                                    TEXT_DISPLAY_NUMBER_OF_TRANSACTIONS ); ?></td>
                            <td class="smallText" align="right">
                                <?php echo $split->display_links( $qryNumRows,
                                    MAX_DISPLAY_SEARCH_RESULTS_FONDY, MAX_DISPLAY_PAGE_LINKS, $_GET['page'],
                                    ( zen_not_null( $selectedStatus ) ? '&f_status='. $selectedStatus : '' ) .
                                    ( zen_not_null( $selectedSortOrder ) ? '&f_sort_order='. $selectedSortOrder : '' ) ); ?></td>
                        </tr>
                        </table>
                    </td>
                </tr>
                </table>
            </td>
<?php
$heading = array();
$contents = array();

switch( $action )
{
    case 'new':
        break;
    case 'edit':
        break;
    case 'delete':
        break;
    default:
        if( is_object( $info ) )
        {
            $heading[] = array( 'text' =>
                '<strong>'. TEXT_INFO_FONDY_HEADING .' #' . $info->id . '</strong>');

            $sql =
                "SELECT *
                FROM `". TABLE_FONDY_PAYMENT_STATUS_HISTORY ."`
                WHERE `f_order_id` = '" . $info->id . "'";
            $statHist = $db->Execute( $sql );
            $noOfRecords = $statHist->RecordCount();

            $contents[] = array(
                'align' => 'center',
                'text' => '<a href="' .
                    zen_href_link( FILENAME_ORDERS,
                        zen_get_all_get_params( array( 'ipnID', 'action' ) ) .
                        'oID=' . $info->zc_order_id .
                        '&f_order_id=' . $info->id .
                        '&action=edit' . '&referer=ipn' ) .
                    '">' .
                    zen_image_button('button_orders.gif', IMAGE_ORDERS) . '</a>'
                );
            $contents[] = array(
                'text' => '<br>'. TABLE_HEADING_NUM_HISTORY_ENTRIES .': '. $noOfRecords );
            $i = 1;

            while( !$statHist->EOF )
            {
                $data = new objectInfo( $statHist->fields );

                $contents[] = array(
                    'text' => '<br>'. TABLE_HEADING_ENTRY_NUM . ': '. $i );
                $contents[] = array(
                    'text' => TABLE_HEADING_DATE_ADDED .': '. zen_datetime_short( $data->timestamp ) );
                $contents[] = array(
                    'text' => TABLE_HEADING_STATUS .': '. $data->status );
                $contents[] = array(
                    'text' => TABLE_HEADING_STATUS_REASON .': '. $data->status_reason );
                $i++;

                $statHist->MoveNext();
            }
        }
        break;
}

if( ( zen_not_null( $heading ) ) && ( zen_not_null( $contents ) ) )
{
    echo '            <td width="25%" valign="top">' . "\n";

    $box = new box;
    echo $box->infoBox( $heading, $contents );

    echo '            </td>' . "\n";
}
?>
            </tr>
            </table>

            </td>
        </tr>
        </table>

    </td>
<!-- body_text_eof //-->
</tr>
</table>
<!-- body_eof //-->

<!-- footer //-->
<?php require( DIR_WS_INCLUDES . 'footer.php' ); ?>
<!-- footer_eof //-->
<br>

</body>
</html>
<?php require( DIR_WS_INCLUDES . 'application_bottom.php' ); ?>
