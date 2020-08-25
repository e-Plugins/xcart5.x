<?php
/* vim: set ts=4 sw=4 sts=4 et: */
/**
 * ***************************************************************************\
* +-----------------------------------------------------------------------------+
* | X-Cart Software license agreement |
* | Copyright (c) 2001-2012 Qualiteam software Ltd <info@x-cart.com> |
* | All rights reserved. |
* +-----------------------------------------------------------------------------+
* | PLEASE READ THE FULL TEXT OF SOFTWARE LICENSE AGREEMENT IN THE "COPYRIGHT" |
* | FILE PROVIDED WITH THIS DISTRIBUTION. THE AGREEMENT TEXT IS ALSO AVAILABLE |
* | AT THE FOLLOWING URL: http://www.x-cart.com/license.php |
* | |
* | THIS AGREEMENT EXPRESSES THE TERMS AND CONDITIONS ON WHICH YOU MAY USE THIS |
* | SOFTWARE PROGRAM AND ASSOCIATED DOCUMENTATION THAT QUALITEAM SOFTWARE LTD |
* | (hereinafter referred to as "THE AUTHOR") OF REPUBLIC OF CYPRUS IS |
* | FURNISHING OR MAKING AVAILABLE TO YOU WITH THIS AGREEMENT (COLLECTIVELY, |
    * | THE "SOFTWARE"). PLEASE REVIEW THE FOLLOWING TERMS AND CONDITIONS OF THIS |
* | LICENSE AGREEMENT CAREFULLY BEFORE INSTALLING OR USING THE SOFTWARE. BY |
* | INSTALLING, COPYING OR OTHERWISE USING THE SOFTWARE, YOU AND YOUR COMPANY |
* | (COLLECTIVELY, "YOU") ARE ACCEPTING AND AGREEING TO THE TERMS OF THIS |
* | LICENSE AGREEMENT. IF YOU ARE NOT WILLING TO BE BOUND BY THIS AGREEMENT, DO |
* | NOT INSTALL OR USE THE SOFTWARE. VARIOUS COPYRIGHTS AND OTHER INTELLECTUAL |
* | PROPERTY RIGHTS PROTECT THE SOFTWARE. THIS AGREEMENT IS A LICENSE AGREEMENT |
* | THAT GIVES YOU LIMITED RIGHTS TO USE THE SOFTWARE AND NOT AN AGREEMENT FOR |
* | SALE OR FOR TRANSFER OF TITLE. THE AUTHOR RETAINS ALL RIGHTS NOT EXPRESSLY |
* | GRANTED BY THIS AGREEMENT. |
* +-----------------------------------------------------------------------------+
* \****************************************************************************
*/

/**
 * Digiwallet
*
* @category X-Cart
* @package X-Cart
* @subpackage Payment interface
* @author Michel Westerink <support@idealplugins.nl>
* @copyright Copyright (c) 2015 <support@idealplugins.nl>
* @license http://www.x-cart.com/license.php X-Cart license agreement
* @version $Id: cc_digiwallet_ideal.php,v 1.0.0 2015/12/16 14:00:00 aim Exp $
* @link http://www.digiwallet.nl/
* @see ____file_see____
*/
require_once 'digiwallet.processor.class.php';
$salt = "e381277";

$result = (new digiwallet_processor("bankwire", "BW", "Digiwallet - Overschrijvingen"))->handleRequest($salt);

x_session_register('cart');

// Check bankwire review status exists or not
$sql = "SELECT * FROM $sql_tbl[custom_order_statuses] WHERE code = 'W'";
$status_result = db_query($sql);
if (db_num_rows($status_result) < 1) {
    db_query("
        INSERT INTO $sql_tbl[custom_order_statuses] (`code`, `color`, `inactive_color`, `show_in_progress`, `only_when_active`, `reserve_products`, `notify_customer`, `notify_orders_dep`, `notify_provider`, `orderby`) VALUES
        ('W', '61C419', '6a6a6a', 'Y', 'N', 'Y', 'N', 'N', 'N', 40)
        ");
}
$sql = "SELECT * FROM $sql_tbl[custom_order_statuses] WHERE code = 'W'";
$status_result = db_query($sql);
if(db_num_rows($status_result) == 1){
    $status_result = mysqli_fetch_object($status_result);
    // insert language
    $check_language = "SELECT * FROM $sql_tbl[languages] WHERE code = 'en' and name = 'order_status_". $status_result->statusid ."_name' limit 1";
    if(db_num_rows(db_query($check_language)) != 1){
        db_query("INSERT INTO $sql_tbl[languages] VALUES ('en','order_status_". $status_result->statusid ."_name','Awaiting Review','Labels')");
    }
    $check_language = "SELECT * FROM $sql_tbl[languages] WHERE code = 'nl' and name = 'order_status_". $status_result->statusid ."_name' limit 1";
    if(db_num_rows(db_query($check_language)) != 1){
        db_query("INSERT INTO $sql_tbl[languages] VALUES ('nl','order_status_". $status_result->statusid ."_name','Awaiting Review','Labels')");
    }
}
// Check result when starting payment
if($result['type'] == 'start_payment')
{
    $digiCore = $result['target_object'];
    $order_id = $result['order_id'];
    if(!empty($digiCore->getTransactionId())){
        if (! function_exists(func_change_order_status)) {
            include_once $xcart_dir . '/include/func/func.order.php';
        }
        // Update transaction as success and payment is pending
        func_change_order_status($order_id, 'Q');
        // Clear cart
        $cart = '';
        // Do not remove as some payment calls take a lot of time
        x_session_save();
        // Redirect to sucess page message
        func_header_location($result['location'] . DIR_CUSTOMER . "/bankwire_success.php?trxid=" . $digiCore->getTransactionId());
        exit();
    } else {
        // Pass to parent. Do nothing
    }
}
else
{
    // Check return result
    $digiCore = $result['target_object'];
    $sale_object = $result['sale_object'];
    $trxid = $result['trxid'];
    $rtlo = $result['rtlo'];
    $testmode = $result['testmode'];
    if(!empty($sale_object->paid))
    {
        echo "Callback already processed";
        exit();
    }
    else
    {
        // Check cancel
        if(isset($_GET['return']) && $_GET['return'] == 'cancel')
        {
            // Update order
            if (! function_exists(func_change_order_status)) {
                include_once $xcart_dir . '/include/func/func.order.php';
            }
            // Update transaction as success and payment is pending
            func_change_order_status($sale_object->order_id, 'D');
            include $xcart_dir . "/payment/payment_ccend.php";
            exit();
        }
        else
        {
            // Check result from Digiwallet
            $bw_paid_amount = 0;
            $paymentIsPartial = false;
            $checksum = md5($trxid . $rtlo . $salt);
            $paid = @$digiCore->checkPayment($trxid, ['checksum' => $checksum, 'once' => 0]);
            $paymentStatus = (bool) $digiCore->getPaidStatus();
            $consumber_info = $digiCore->getConsumerInfo();
            if (!empty($consumber_info) && $consumber_info['bw_paid_amount'] > 0) {
                if ($consumber_info['bw_paid_amount'] < $consumber_info['bw_due_amount']) {
                    $paymentIsPartial = true;
                    $bw_paid_amount = $consumber_info['bw_paid_amount'];
                }
            }
            if($testmode){
                // Don't check test mode status
                // $paymentStatus = true;
            }
            if($paymentStatus)
            {
                // Update sale table
                $sql = "UPDATE `digiwallet_transactions` set `paid` = now(), `digi_response` = '". addslashes($digiCore->getMoreInformation()) . "' WHERE `digi_txid` = '" . $trxid . "'";
                $result = db_query($sql);
                // Update order
                if (! function_exists(func_change_order_status)) {
                    include_once $xcart_dir . '/include/func/func.order.php';
                }
                // Update transaction as success and payment is pending
                if($paymentIsPartial) {
                    func_change_order_status($sale_object->order_id, 'W'); // Awaiting review payment
                } else {
                    func_change_order_status($sale_object->order_id, 'P');
                }
                // Return message
                $result_message = "Order processed";
            }
            else
            {
                $result_message = "Order isn't processed";
            }
            // Print result content
            if(isset($_GET['return']) && $_GET['return'] == 'success')
            {
                include $xcart_dir . "/payment/payment_ccend.php";
                exit();
            }
            else
            {
                echo $result_message;
                exit();
            }
        }
    }
}
