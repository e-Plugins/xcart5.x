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
 *
 */
require_once './auth.php';
require "Digiwallet/ClientCore.php";

class digiwallet_processor
{

    public $target_processor_file_name;

    public $target_processor_code;

    public $target_processor_method_name;

    /**
     * Tax applying percent
     * @var array
     */
    protected $array_tax = [
        1 => 21,
        2 => 6,
        3 => 0,
        4 => 'none'
    ];
    /***
     * Get product tax by Digiwallet
     * @param unknown $val
     * @return number
     */
    private function getTax($val)
    {
        if(empty($val)) return 4; // No tax
        else if($val >= 21) return 1;
        else if($val >= 6) return 2;
        else return 3;
    }
    /**
     * Contructor
     * @param unknown $filename
     * @param unknown $code
     * @param unknown $methodname
     */
    public function __construct($filename, $code, $methodname)
    {
        $this->target_processor_code = $code;
        $this->target_processor_file_name = $filename;
        $this->target_processor_method_name = $methodname;
    }

    /**
     * Clean input string
     * @param unknown $str
     * @return unknown
     */
    public function clean($str)
    {
        $str = @trim($str);

        if (get_magic_quotes_gpc()) {
            $str = stripslashes($str);
        }

        return mysqli_escape_string(($str));
    }

    /**
     * Format phonenumber by NL/BE
     *
     * @param unknown $country
     * @param unknown $phone
     * @return unknown
     */
    private static function format_phone($country, $phone) {
        $function = 'format_phone_' . strtolower($country);
        if(method_exists('digiwallet_processor', $function)) {
            return self::$function($phone);
        }
        else {
            echo "unknown phone formatter for country: ". $function;
            exit;
        }
        return $phone;
    }
    /**
     * Format phone number
     *
     * @param unknown $phone
     * @return string|mixed
     */
    private static function format_phone_nld($phone) {
        // note: making sure we have something
        if(!isset($phone{3})) { return ''; }
        // note: strip out everything but numbers
        $phone = preg_replace("/[^0-9]/", "", $phone);
        $length = strlen($phone);
        switch($length) {
            case 9:
                return "+31".$phone;
                break;
            case 10:
                return "+31".substr($phone, 1);
                break;
            case 11:
            case 12:
                return "+".$phone;
                break;
            default:
                return $phone;
                break;
        }
    }

    /**
     * Format phone number
     *
     * @param unknown $phone
     * @return string|mixed
     */
    private static function format_phone_bel($phone) {
        // note: making sure we have something
        if(!isset($phone{3})) { return ''; }
        // note: strip out everything but numbers
        $phone = preg_replace("/[^0-9]/", "", $phone);
        $length = strlen($phone);
        switch($length) {
            case 9:
                return "+32".$phone;
                break;
            case 10:
                return "+32".substr($phone, 1);
                break;
            case 11:
            case 12:
                return "+".$phone;
                break;
            default:
                return $phone;
                break;
        }
    }
    /**
     * Breadown street address
     * @param unknown $street
     * @return NULL[]|string[]|unknown[]
     */
    private static function breakDownStreet($street)
    {
        $out = [];
        $addressResult = null;
        preg_match("/(?P<address>\D+) (?P<number>\d+) (?P<numberAdd>.*)/", $street, $addressResult);
        if(!$addressResult) {
            preg_match("/(?P<address>\D+) (?P<number>\d+)/", $street, $addressResult);
        }
        $out['street'] = array_key_exists('address', $addressResult) ? $addressResult['address'] : null;
        $out['houseNumber'] = array_key_exists('number', $addressResult) ? $addressResult['number'] : null;
        $out['houseNumberAdd'] = array_key_exists('numberAdd', $addressResult) ? trim(strtoupper($addressResult['numberAdd'])) : null;
        return $out;
    }

    /**
     * Using unified client request
     * @param string $salt
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handleUnifiedRequest($salt = "")
    {
        global $sql_tbl, $cart, $secure_oid, $current_location, $xcart_dir, $login_type, $top_message;

        $module_params = func_query_first("SELECT * FROM $sql_tbl[ccprocessors] WHERE processor='cc_digiwallet_$this->target_processor_file_name.php'");
        if ($module_params['testmode'] == 'Y') {
            $testmode = true;
        } else {
            $testmode = false;
        }

        if (! isset($REQUEST_METHOD)) {
            $REQUEST_METHOD = $_SERVER["REQUEST_METHOD"];
        }

        if (! func_is_active_payment("cc_digiwallet_$this->target_processor_file_name.php")) {
            exit();
        }

        // Transaction ID check
        $sessionID_check = db_query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$sql_tbl[cc_pp3_data]' AND COLUMN_NAME = 'sessid'");
        $sessionName = 'sessionid';
        if (db_num_rows($sessionID_check) > 0) {
            $sessionName = 'sessid';
        }
        // Validate trxid
        $trxid = ((isset($_GET['trxid']) && ! empty($_GET['trxid'])) ? $_GET['trxid'] : ((isset($_POST['trxid']) && ! empty($_POST['trxid'])) ? $_POST['trxid'] : false));
        if(empty($trxid)) {
            $trxid = (isset($_GET['transactionID']) && ! empty($_GET['transactionID'])) ? $_GET['transactionID'] : ((isset($_POST['transactionID']) && ! empty($_POST['transactionID'])) ? $_POST['transactionID'] : false);
        }
        // Check result
        if ($trxid && ($_GET['return'] == 'success' || $_GET['return'] == 'cancel') || $_GET['return'] == 'callback')
        {
            // Load X-Cart data
            x_load("http");
            x_session_register("cart");
            x_session_register("secure_oid");

            // X-Cart data for bill | REQUIRED!
            $bill_output = array();

            $sql = "SELECT * FROM `digiwallet_transactions` WHERE `digi_txid` = '" . $trxid . "' ORDER BY id DESC LIMIT 1";
            $result = db_query($sql);
            if (db_num_rows($result) != 1) {
                echo 'Error, No transaction found with id: ' . htmlspecialchars($trxid);
                exit();
            }

            $tpOrder = db_fetch_array($result);

            include_once dirname(__FILE__) . '/digiwallet.class.php';
            $digiCore = new \Digiwallet\ClientCore($module_params['param01'], $this->target_processor_code, 'nl');

            $x_order_id = $tpOrder['order_id'];
            // Update payment method
            db_query("UPDATE $sql_tbl[orders] SET payment_method = '$this->target_processor_method_name' WHERE orderid='" . $this->clean($x_order_id) . "' LIMIT 1");
            $skey = $tpOrder['order_id'];

            $paid = $digiCore->checkTransaction($module_params['param02'], $trxid);

            if ($paid || $testmode) {
                $status = 'success';
                $status_code = 1;
                $bill_message = 'Accepted';
                // Update sale table to success status
                $sql = "UPDATE `digiwallet_transactions` set `paid` = now(), `digi_response` = '". ($testmode ? 'Test Mode' : '') . "' WHERE `digi_txid` = '" . $trxid . "'";
                $result = db_query($sql);
            } else {
                $status_code = 2;
                $status = 'open';
                $bill_message = $digiCore->getErrorMessage();
            }

            $bill_output['sessid'] = $sessionid;
            $bill_output["billmes"] = $status . " (" . date("d/m/Y h:i:s") . ")";
            $bill_output["code"] = $status_code;

            if (! function_exists('func_change_order_status')) {
                include_once $xcart_dir . '/include/func/func.order.php';
            }

            if ($_GET['return'] == 'success' || $_GET['return'] == 'cancel')
            {
                if ($paid) {
                    func_change_order_status($tpOrder['order_id'], 'P');
                    // Redirect to end order
                    include $xcart_dir . "/payment/payment_ccend.php";
                    exit();
                } else {
                    $sessionName = 'sessionid';
                    if (db_num_rows($sessionID_check) > 0) {
                        $sessionName = 'sessid';
                    }
                    $query = db_query("SELECT * FROM $sql_tbl[cc_pp3_data] WHERE ref='$skey'");
                    $qResult = db_fetch_array($query);
                    db_query("UPDATE $sql_tbl[cc_pp3_data] SET param1 = 'error_message.php?$XCART_SESSION_NAME=$qResult[$sessionName]&error=error_ccprocessor_error&bill_message=Order+is+cancelled+', param3 = 'error' , is_callback = 'N' WHERE ref = '$skey'");
                    $top_message = array ('content' => $bill_message, 'type' => 'E');
                    func_header_location('../cart.php?mode=checkout');
                    exit();
                }
            }
            elseif ($_GET['return'] == 'callback')
            {
                // Update status to "processed"
                if ($paid) {
                    func_change_order_status($tpOrder['order_id'], 'P');
                    echo 'Paid...';
                } else {
                    func_change_order_status($tpOrder['order_id'], 'D');
                    echo 'Received';
                }
                die();
            }
        }
        else
        {
            if (! defined('XCART_START')) {
                header("Location: ../");
                die("Access denied");
            }

            x_load("http");
            x_session_register("cart");
            x_session_register("secure_oid");

            include_once dirname(__FILE__) . '/digiwallet.class.php';

            // User's information for billing and shipping address
            $userinfo = func_userinfo(0, $login_type, false, false, 'H');

            $digiCore = new \Digiwallet\ClientCore($module_params['param01'], $this->target_processor_code, 'nl');
            $amount = round(100 * $cart['total_cost']);
            $formData = array(
                'amount' => $amount,
                'inputAmount' => $amount,
                'consumerEmail' => $userinfo['email'],
                'description' => 'Order #' . $secure_oid[0],
                'returnUrl' => $current_location . "/payment/cc_digiwallet_$this->target_processor_file_name.php?return=success",
                'reportUrl' => $current_location . "/payment/cc_digiwallet_$this->target_processor_file_name.php?return=callback",
                'cancelUrl' => $current_location . "/payment/cc_digiwallet_$this->target_processor_file_name.php?return=cancel",
                'test' => $testmode ? 1 : 0
            );

            /** @var \Digiwallet\Packages\Transaction\Client\Response\CreateTransaction $clientResult */
            $clientResult = $digiCore->createTransaction($module_params['param02'], $formData);

            $sessionID_check = db_query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$sql_tbl[cc_pp3_data]' AND COLUMN_NAME = 'sessid'");
            $sessionName = 'sessionid';
            if (db_num_rows($sessionID_check) > 0) {
                $sessionName = 'sessid';
            }
            if ($clientResult) {
                db_query("REPLACE INTO $sql_tbl[cc_pp3_data] (ref,$sessionName,trstat) VALUES ('" . addslashes($secure_oid[0]) . "','" . $XCARTSESSID . "','TPIDE|" . implode('|', $secure_oid) . "')");

                $sql = "CREATE TABLE IF NOT EXISTS `digiwallet_transactions` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `order_id` varchar(64) NOT NULL DEFAULT '',
                  `method` varchar(6) DEFAULT NULL,
                  `amount` int(11) DEFAULT NULL,
                  `digi_txid` varchar(64) DEFAULT NULL,
                  `digi_response` varchar(128) DEFAULT NULL,
                  `paid` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `order_id` (`order_id`)
                ) ENGINE=InnoDB";
                db_query($sql);
                // For older version, need to update table to add new field
                $check_sql = "SHOW COLUMNS FROM `digiwallet_transactions` LIKE 'more';";
                $result = db_query($check_sql);
                if (db_num_rows($result) != 1) {
                    // Add more columns
                    db_query("ALTER TABLE `digiwallet_transactions` ADD `more` TEXT default null;");
                }
                db_query("INSERT INTO `digiwallet_transactions` SET `order_id` = '" . $secure_oid[0] . "', `method` = '$this->target_processor_code', `amount` = '" . $amount . "', `digi_txid` = '" . $clientResult->transactionId() . "', `more` = '" . addslashes($clientResult->transactionKey()) . "'");
                func_header_location($clientResult->launchUrl());
                exit();
            } else {
                // Start payment error
                $top_message = array ('content' => $digiCore->getErrorMessage(), 'type' => 'E');
                func_header_location('../cart.php?mode=checkout');
                exit();
            }
        }
        exit();
    }
    /**
     * Handle payment process
     */
    public function handleRequest($salt = "")
    {
        global $sql_tbl, $cart, $secure_oid, $current_location, $xcart_dir, $login_type, $top_message;

        $module_params = func_query_first("SELECT * FROM $sql_tbl[ccprocessors] WHERE processor='cc_digiwallet_$this->target_processor_file_name.php'");
        if ($module_params['testmode'] == 'Y') {
            $testmode = true;
        } else {
            $testmode = false;
        }

        if (! isset($REQUEST_METHOD)) {
            $REQUEST_METHOD = $_SERVER["REQUEST_METHOD"];
        }

        if (! func_is_active_payment("cc_digiwallet_$this->target_processor_file_name.php")) {
            exit();
        }

        // Transaction ID check
        $sessionID_check = db_query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$sql_tbl[cc_pp3_data]' AND COLUMN_NAME = 'sessid'");
        $sessionName = 'sessionid';
        if (db_num_rows($sessionID_check) > 0) {
            $sessionName = 'sessid';
        }
        // Validate trxid
        $trxid = ((isset($_GET['trxid']) && ! empty($_GET['trxid'])) ? $_GET['trxid'] : ((isset($_POST['trxid']) && ! empty($_POST['trxid'])) ? $_POST['trxid'] : false));
        if($this->target_processor_code == "PYP") {
            // Check PYP parameters
            if($_GET['return'] == 'callback') {
                // Report URL called
                $trxid = ((isset($_GET['acquirerID']) && ! empty($_GET['acquirerID'])) ? $_GET['acquirerID'] : ((isset($_POST['acquirerID']) && ! empty($_POST['acquirerID'])) ? $_POST['acquirerID'] : false));
            } else {
                // Return/Cancel URL called
                $trxid = ((isset($_GET['paypalid']) && ! empty($_GET['paypalid'])) ? $_GET['paypalid'] : ((isset($_POST['paypalid']) && ! empty($_POST['paypalid'])) ? $_POST['paypalid'] : false));
            }
        } else if($this->target_processor_code == "AFP") {
            // Afterpay
            $trxid = ((isset($_GET['invoiceID']) && ! empty($_GET['invoiceID'])) ? $_GET['invoiceID'] : ((isset($_POST['invoiceID']) && ! empty($_POST['invoiceID'])) ? $_POST['invoiceID'] : false));
        }
        // Check result
        if ($trxid && ($_GET['return'] == 'success' || $_GET['return'] == 'cancel') || $_GET['return'] == 'callback')
        {
            // Load X-Cart data
            x_load("http");
            x_session_register("cart");
            x_session_register("secure_oid");

            // X-Cart data for bill | REQUIRED!
            $bill_output = array();

            $sql = "SELECT * FROM `digiwallet_transactions` WHERE `digi_txid` = '" . $trxid . "' ORDER BY id DESC LIMIT 1";
            $result = db_query($sql);
            if (db_num_rows($result) != 1) {
                echo 'Error, No transaction found with id: ' . htmlspecialchars($trxid);
                exit();
            }

            $tpOrder = db_fetch_array($result);

            include_once dirname(__FILE__) . '/digiwallet.class.php';
            $digiCore = new DigiwalletCore($this->target_processor_code, $module_params['param01'], "nl", $testmode);
            $digiCore->setSalt($salt);

            $x_order_id = $tpOrder['order_id'];
            // Update payment method
            db_query("UPDATE $sql_tbl[orders] SET payment_method = '$this->target_processor_method_name' WHERE orderid='" . $this->clean($x_order_id) . "' LIMIT 1");
            $skey = $tpOrder['order_id'];
            // Return result to continue handle the response on child classes for Afterpay and Bankwire - Overschrijvingen
            if(in_array($digiCore->getPayMethod(), ["AFP", "BW"])){
                return [
                    'type' => 'return_payment',
                    'target_object' => $digiCore,
                    'sale_object' => $tpOrder,
                    'trxid' => $trxid,
                    'rtlo' => $module_params['param01'],
                    'testmode' => $testmode
                ];
            }

            $paid = @$digiCore->checkPayment($trxid);
            if ($paid) {
                $status = 'success';
                $status_code = 1;
                $bill_message = 'Accepted';
                // Update sale table to success status
                $sql = "UPDATE `digiwallet_transactions` set `paid` = now(), `digi_response` = '". addslashes($digiCore->getMoreInformation()) . "' WHERE `digi_txid` = '" . $trxid . "'";
                $result = db_query($sql);
            } else {
                $status_code = 2;
                $status = 'open';
                $bill_message = $digiCore->getErrorMessage();
            }

            $bill_output['sessid'] = $sessionid;
            $bill_output["billmes"] = $status . " (" . date("d/m/Y h:i:s") . ")";
            $bill_output["code"] = $status_code;

            if ($_GET['return'] == 'success' || $_GET['return'] == 'cancel')
            {
                if ($paid) {
                    if (! function_exists('func_change_order_status')) {
                        include_once $xcart_dir . '/include/func/func.order.php';
                    }
                    func_change_order_status($tpOrder['order_id'], 'P');
                    // Redirect to end order
                    $xcart_catalogs['customer'] = "..";
                    include $xcart_dir . "/payment/payment_ccend.php";
                    exit();
                } else {
                    $sessionName = 'sessionid';
                    if (db_num_rows($sessionID_check) > 0) {
                        $sessionName = 'sessid';
                    }
                    $query = db_query("SELECT * FROM $sql_tbl[cc_pp3_data] WHERE ref='$skey'");
                    $qResult = db_fetch_array($query);
                    db_query("UPDATE $sql_tbl[cc_pp3_data] SET param1 = 'error_message.php?$XCART_SESSION_NAME=$qResult[$sessionName]&error=error_ccprocessor_error&bill_message=Order+is+cancelled+', param3 = 'error' , is_callback = 'N' WHERE ref = '$skey'");
                    $top_message = array ('content' => $bill_message, 'type' => 'E');
                    func_header_location('../cart.php?mode=checkout');
                    exit();
                }
            }
            elseif ($_GET['return'] == 'callback')
            {
                if (! function_exists('func_change_order_status')) {
                    include_once $xcart_dir . '/include/func/func.order.php';
                }
                // Update status to "processed"
                if ($paid) {
                    func_change_order_status($tpOrder['order_id'], 'P');
                    echo 'Paid...';
                } else {
                    func_change_order_status($tpOrder['order_id'], 'D');
                    echo 'Received';
                }
                die();
            }
        }
        else
        {
            if (! defined('XCART_START')) {
                header("Location: ../");
                die("Access denied");
            }

            x_load("http");
            x_session_register("cart");
            x_session_register("secure_oid");

            include_once dirname(__FILE__) . '/digiwallet.class.php';

            // User's information for billing and shipping address
            $userinfo = func_userinfo(0, $login_type, false, false, 'H');
            $digiCore = new DigiwalletCore($this->target_processor_code, $module_params['param01'], "nl", $testmode);

            $amount = round(100 * $cart['total_cost']);
            $digiCore->setAmount($amount);
            $digiCore->setDescription('Order #' . $secure_oid[0]);
            $digiCore->setReturnUrl($current_location . "/payment/cc_digiwallet_$this->target_processor_file_name.php?return=success");
            $digiCore->setCancelUrl($current_location . "/payment/cc_digiwallet_$this->target_processor_file_name.php?return=cancel");
            $digiCore->setReportUrl($current_location . "/payment/cc_digiwallet_$this->target_processor_file_name.php?return=callback");

            $digiCore->setSalt($salt);
            $digiCore->bindParam('email', $userinfo['email']);
            $digiCore->bindParam('userip', $_SERVER["REMOTE_ADDR"]);

            if($digiCore->getPayMethod() == "AFP"){
                // Adding more information for Afterpay method
                $b_country = $userinfo['b_country'];
                $s_country = $userinfo['s_country'];
                $b_country = (strtoupper($b_country) == 'BE' ? 'BEL' : 'NLD');
                $s_country = (strtoupper($s_country) == 'BE' ? 'BEL' : 'NLD');
                // Build billing address
                $streetParts = self::breakDownStreet("");
                if(!isset($userinfo['b_address_2']) || empty($userinfo['b_address_2'])){
                    $streetParts = self::breakDownStreet($userinfo['b_address']);
                }
                $digiCore->bindParam('billingstreet', empty($streetParts['street']) ? $userinfo['b_address'] : $streetParts['street']);
                $digiCore->bindParam('billinghousenumber', empty($streetParts['houseNumber'].$streetParts['houseNumberAdd']) ? $userinfo['b_address_2'] : $streetParts['houseNumber'].$streetParts['houseNumberAdd']);
                $digiCore->bindParam('billingpostalcode', $userinfo['b_zipcode']);
                $digiCore->bindParam('billingcity', $userinfo['b_city']);
                $digiCore->bindParam('billingpersonemail', $userinfo['email']);
                $digiCore->bindParam('billingpersoninitials', "");
                $digiCore->bindParam('billingpersongender', "");
                $digiCore->bindParam('billingpersonsurname', $userinfo['b_firstname'] . ((!empty($userinfo['b_lastname'])) ? " " . $userinfo['b_lastname'] : ""));
                $digiCore->bindParam('billingcountrycode', $b_country);
                $digiCore->bindParam('billingpersonlanguagecode', $b_country);
                $digiCore->bindParam('billingpersonbirthdate', "");
                $digiCore->bindParam('billingpersonphonenumber',  self::format_phone($b_country, $userinfo['b_phone']));
                // Build shipping address
                $streetParts = self::breakDownStreet("");
                if(!isset($userinfo['s_address_2']) || empty($userinfo['s_address_2'])){
                    $streetParts = self::breakDownStreet($userinfo['s_address']);
                }
                $digiCore->bindParam('shippingstreet', empty($streetParts['street']) ? $userinfo['s_address'] : $streetParts['street']);
                $digiCore->bindParam('shippinghousenumber', empty($streetParts['houseNumber'].$streetParts['houseNumberAdd']) ? $userinfo['s_address_2'] : $streetParts['houseNumber'].$streetParts['houseNumberAdd']);
                $digiCore->bindParam('shippingpostalcode',  $userinfo['s_zipcode']);
                $digiCore->bindParam('shippingcity',  $userinfo['s_city']);
                $digiCore->bindParam('shippingpersonemail',  $userinfo['email']);
                $digiCore->bindParam('shippingpersoninitials', "");
                $digiCore->bindParam('shippingpersongender', "");
                $digiCore->bindParam('shippingpersonsurname',  $userinfo['s_firstname'] . ((!empty($userinfo['s_lastname'])) ? " " . $userinfo['s_lastname'] : ""));
                $digiCore->bindParam('shippingcountrycode', $s_country);
                $digiCore->bindParam('shippingpersonlanguagecode', $s_country);
                $digiCore->bindParam('shippingpersonbirthdate', "");
                $digiCore->bindParam('shippingpersonphonenumber',  self::format_phone($s_country, $userinfo['s_phone']));
                // Process to add order info
                $invoice_lines = null;
                $total_amount_by_product = 0;
                if(!empty($cart['products'])){
                    foreach ($cart['products'] as $product){
                        $invoice_lines[] = [
                            'productCode' => $product['productid'],
                            'productDescription' => $product['product'],
                            'quantity' => (int) $product['amount'],
                            'price' => $product['price'],
                            'taxCategory' => ($cart['total_cost'] > 0) ? $this->getTax(100 * $cart['tax_cost'] / $cart['total_cost']) : 3
                        ];
                        $total_amount_by_product += $product['price'];
                    }
                }
                // Update to fix the total amount and item price
                if($total_amount_by_product < $cart['total_cost']){
                    $invoice_lines[] = [
                        'productCode' => "000000",
                        'productDescription' => "Other fee (shipping, additional fees)",
                        'quantity' => 1,
                        'price' => $cart['total_cost'] - $total_amount_by_product,
                        'taxCategory' => 3
                    ];
                }
                // Add to invoice data
                if($invoice_lines != null && !empty($invoice_lines)){
                    $digiCore->bindParam('invoicelines', json_encode($invoice_lines));
                }
            }
            $url = @$digiCore->startPayment(true);

            $sessionID_check = db_query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '$sql_tbl[cc_pp3_data]' AND COLUMN_NAME = 'sessid'");
            $sessionName = 'sessionid';
            if (db_num_rows($sessionID_check) > 0) {
                $sessionName = 'sessid';
            }
            if ($url) {
                db_query("REPLACE INTO $sql_tbl[cc_pp3_data] (ref,$sessionName,trstat) VALUES ('" . addslashes($secure_oid[0]) . "','" . $XCARTSESSID . "','TPIDE|" . implode('|', $secure_oid) . "')");

                $sql = "CREATE TABLE IF NOT EXISTS `digiwallet_transactions` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `order_id` varchar(64) NOT NULL DEFAULT '',
                  `method` varchar(6) DEFAULT NULL,
                  `amount` int(11) DEFAULT NULL,
                  `digi_txid` varchar(64) DEFAULT NULL,
                  `digi_response` varchar(128) DEFAULT NULL,
                  `paid` datetime DEFAULT NULL,
                  PRIMARY KEY (`id`),
                  KEY `order_id` (`order_id`)
                ) ENGINE=InnoDB";
                db_query($sql);
                // For older version, need to update table to add new field
                $check_sql = "SHOW COLUMNS FROM `digiwallet_transactions` LIKE 'more';";
                $result = db_query($check_sql);
                if (db_num_rows($result) != 1) {
                    // Add more columns
                    $check_sql = "ALTER TABLE `digiwallet_transactions` ADD `more` TEXT default null;";
                    db_query($check_sql);
                }
                $sql = "INSERT INTO `digiwallet_transactions` SET `order_id` = '" . $secure_oid[0] . "', `method` = '$this->target_processor_code', `amount` = '" . $amount . "', `digi_txid` = '" . $digiCore->getTransactionId() . "', `more` = '" . addslashes($digiCore->getMoreInformation()) . "'";
                db_query($sql);
                // Return result to continue handle the response on child classes for Afterpay and Bankwire - Overschrijvingen
                if(in_array($digiCore->getPayMethod(), ["AFP", "BW"])){
                    return [
                        'type' => 'start_payment',
                        'target_object' => $digiCore,
                        'location' => $current_location,
                        'order_id' => $secure_oid[0]
                    ];
                }
                func_header_location($url);
                exit();
            } else {
                // Start payment error
                $top_message = array ('content' => $digiCore->getErrorMessage(), 'type' => 'E');
                func_header_location('../cart.php?mode=checkout');
                exit();
            }
        }
        exit();
    }
    /**
     * Check if refund feature is available for this transaction
     *
     * @param String $paymentid
     * @param int $orderid
     */
    public function isRefundAvailable($paymentid, $orderid)
    {
        global $sql_tbl, $cart, $secure_oid, $current_location, $xcart_dir, $login_type;

        $module_params = func_query_first("SELECT * FROM $sql_tbl[ccprocessors] WHERE processor='cc_digiwallet_$this->target_processor_file_name.php'");
        $sale = func_query_first("SELECT * FROM digiwallet_transactions WHERE order_id = '$orderid' AND paid IS NOT NULL");

        return !empty($sale) && !empty($module_params['param02']);
    }
    /**
     * Refund order via Digiwallet
     *
     * @param Order object $order
     * @param int $amount
     */
    public function refund_order($order, $amount)
    {
        global $sql_tbl, $cart, $secure_oid, $current_location, $xcart_dir, $login_type;

        $orderid = $order['order']['orderid'];
        $paymentid = $order['order']['paymentid'];
        // Check payment method and transaction
        if($this->isRefundAvailable($paymentid, $orderid)){
            // Get related params
            $module_params = func_query_first("SELECT * FROM $sql_tbl[ccprocessors] WHERE processor='cc_digiwallet_$this->target_processor_file_name.php'");
            $sale = func_query_first("SELECT * FROM digiwallet_transactions WHERE order_id = '$orderid' AND paid IS NOT NULL");

            $internalNote =  "Refunding Order with orderId: " . $orderid . " - Digiwallet transactionId: " . $sale['digi_txid'] . " - Total price: " . $sale['amount']/100;
            $consumerName = "GUEST";
            if(!empty($order['userinfo'])) {
                $consumerName = $order['userinfo']['b_firstname'] . ((!empty($order['userinfo']['b_lastname'])) ? " " . $order['userinfo']['b_lastname'] : "");
            }
            $refundData = array(
                'paymethodID' => $this->target_processor_code,
                'transactionID' => $sale['digi_txid'],
                'amount' => (int) ($amount * 100), // Parse amount to Int and convert to cent value
                'description' => 'Refunding order #' . $orderid,
                'internalNote' => $internalNote,
                'consumerName' => $consumerName
            );
            if ($module_params['testmode'] == 'Y') {
                $testmode = true;
            } else {
                $testmode = false;
            }
            // Init Tagetpay Object
            include_once dirname(__FILE__) . '/digiwallet.class.php';
            $digiCore = new DigiwalletCore($this->target_processor_code, $module_params['param01'], "nl", $testmode);
            if(!$testmode && !$digiCore->refundInvoice($refundData, $module_params['param02'])) {
                return array(false, "Digiwallet refunding error: {$digiCore->getRawErrorMessage()}", null);
            }
            return array(true, 'Payment refunded successfully', null);
        } else {
            return array(false, 'Payment method does not support refunding or the transaction is not completed', null);
        }
    }
}
