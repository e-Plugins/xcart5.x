<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * @file Provides support for Digiwallet iDEAL, Mister Cash, Sofort Banking, Credit and Paysafe
*
* @author Yellow Melon B.V.
*         @url http://www.idealplugins.nl
*/
namespace XLite\Module\Digiwallet\Payment\Processor;

use XLite\Module\Digiwallet\Payment\Base\TargetPayPlugin;
use XLite\Module\Digiwallet\Payment\Model\AfterpayValidationException;

class Afterpay extends TargetPayPlugin
{

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

    /**
     * Error reject
     * @var unknown
     */
    protected $reject_error;

    /**
     * Redirect to enrichment url
     * @var unknown
     */
    protected $enrichment_url;
    /**
     * The contructor
     */
    public function __construct()
    {
        $this->payMethod = "AFP";
        $this->currency = "EUR";
        $this->language = 'nl';
        $this->allow_nobank = true;
    }

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
     * Format phonenumber by NL/BE
     *
     * @param unknown $country
     * @param unknown $phone
     * @return unknown
     */
    private static function format_phone($country, $phone) {
        $function = 'format_phone_' . strtolower($country);
        if(method_exists('XLite\Module\Digiwallet\Payment\Processor\Afterpay', $function)) {
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
     * Init some params for starting payment
     * {@inheritDoc}
     * @see \XLite\Module\Digiwallet\Payment\Base\TargetPayPlugin::getFormURL()
     */
    protected function getFormURL()
    {
        $this->initTargetPayment();
        // Add invoice lines to payment
        $invoice_lines = null;
        $total_amount_by_product = 0;
        foreach ($this->getOrder()->getItems() as $item) {
            $invoice_lines[] = [
                'productCode' => (string) $item->getProduct()->getId(),
                'productDescription' => $item->getProduct()->getName(),
                'quantity' => (int) $item->getAmount(),
                'price' => $item->getPrice(),
                'taxCategory' => ($this->getOrder()->getSubtotal() > 0) ? $this->getTax(100 * $this->getOrder()->getSurchargeSum() / $this->getOrder()->getSubtotal()) : 3
            ];
            $total_amount_by_product += $item->getPrice();
        }
        // Update to fix the total amount and item price
        if($total_amount_by_product < $this->transaction->getValue()){
            $invoice_lines[] = [
                'productCode' => "000000",
                'productDescription' => "Other fee (shipping, additional fees)",
                'quantity' => 1,
                'price' => $this->transaction->getValue() - $total_amount_by_product,
                'taxCategory' => 3
            ];
        }
        // Add to payment data
        if($invoice_lines != null && !empty($invoice_lines)){
            $this->digiCore->bindParam('invoicelines', json_encode($invoice_lines));
        }
        // Build billing address
        $billingstreet = "";
        $billingpostcode = "";
        $billingcity = "";
        $billingsurename = "";
        $billingcountrycode = "";
        $billingphonenumber = "";
        // Build shipping address
        $shippingstreet = "";
        $shippingpostcode = "";
        $shippingcity = "";
        $shippingsurename = "";
        $shippingcountrycode = "";
        $shippingphonenumber = "";
        foreach ($this->getOrder()->getAddresses() as $add){
            /** @var \XLite\Model\Address $add */
            if($add->getIsBilling()) {
                $item = $this->getAddressSectionData($add);
                if(!empty($add->getCountry())){
                    $billingcountrycode = $add->getCountry()->getCode3();
                }
                $billingstreet = isset($item['street']) ? $item['street']['value'] : "";
                $billingpostcode = isset($item['zipcode']) ? $item['zipcode']['value'] : "";
                $billingcity = isset($item['city']) ? $item['city']['value'] : "";
                $billingsurename = isset($item['firstname']) ? $item['firstname']['value'] : "";
                $billingsurename .= isset($item['lastname']) ? " " . $item['lastname']['value'] : "";
                $billingphonenumber = isset($item['phone']) ? " " . $item['phone']['value'] : "";
            }
            // Shipping address
            if($add->getIsShipping()) {
                $item = $this->getAddressSectionData($add);
                if(!empty($add->getCountry())){
                    $shippingcountrycode = $add->getCountry()->getCode3();
                }
                $shippingstreet = isset($item['street']) ? $item['street']['value'] : "";
                $shippingpostcode = isset($item['zipcode']) ? $item['zipcode']['value'] : "";
                $shippingcity = isset($item['city']) ? $item['city']['value'] : "";
                $shippingsurename = isset($item['firstname']) ? $item['firstname']['value'] : "";
                $shippingsurename .= isset($item['lastname']) ? " " . $item['lastname']['value'] : "";
                $shippingphonenumber = isset($item['phone']) ? " " . $item['phone']['value'] : "";
            }
        }
        $billingcountrycode = (strtoupper($billingcountrycode) == 'BE' ? 'BEL' : 'NLD');
        $shippingcountrycode = (strtoupper($shippingcountrycode) == 'BE' ? 'BEL' : 'NLD');
        // Build shipping address
        $streetParts = self::breakDownStreet($billingstreet);
        $this->digiCore->bindParam('billingstreet', empty($streetParts['street']) ? $billingstreet : $streetParts['street']);
        $this->digiCore->bindParam('billinghousenumber', $streetParts['houseNumber'].$streetParts['houseNumberAdd']);
        $this->digiCore->bindParam('billingpostalcode', $billingpostcode);
        $this->digiCore->bindParam('billingcity', $billingcity);
        $this->digiCore->bindParam('billingpersonemail', $this->getOrder()->getProfile()->getLogin());
        $this->digiCore->bindParam('billingpersoninitials', "");
        $this->digiCore->bindParam('billingpersongender', "");
        $this->digiCore->bindParam('billingpersonsurname', $billingsurename);
        $this->digiCore->bindParam('billingcountrycode', $billingcountrycode);
        $this->digiCore->bindParam('billingpersonlanguagecode', $billingcountrycode);
        $this->digiCore->bindParam('billingpersonbirthdate', "");
        $this->digiCore->bindParam('billingpersonphonenumber', self::format_phone($billingcountrycode, $billingphonenumber));
        // Build shipping address
        $streetParts = self::breakDownStreet($shippingstreet);
        $this->digiCore->bindParam('shippingstreet', empty($streetParts['street']) ? $shippingstreet : $streetParts['street']);
        $this->digiCore->bindParam('shippinghousenumber', $streetParts['houseNumber'].$streetParts['houseNumberAdd']);
        $this->digiCore->bindParam('shippingpostalcode', $shippingpostcode);
        $this->digiCore->bindParam('shippingcity', $shippingcity);
        $this->digiCore->bindParam('shippingpersonemail', $this->getOrder()->getProfile()->getLogin());
        $this->digiCore->bindParam('shippingpersoninitials', "");
        $this->digiCore->bindParam('shippingpersongender', "");
        $this->digiCore->bindParam('shippingpersonsurname', $shippingsurename);
        $this->digiCore->bindParam('shippingcountrycode', $shippingcountrycode);
        $this->digiCore->bindParam('shippingpersonlanguagecode', $shippingcountrycode);
        $this->digiCore->bindParam('shippingpersonbirthdate', "");
        $this->digiCore->bindParam('shippingpersonphonenumber', self::format_phone($shippingcountrycode, $shippingphonenumber));

        return parent::getFormURL();
    }


    /**
     * Return specific data for address entry. Helper.
     *
     * @param \XLite\Model\Address $address   Address
     * @param boolean              $showEmpty Show empty fields OPTIONAL
     *
     * @return array
     */
    protected function getAddressSectionData(\XLite\Model\Address $address, $showEmpty = false)
    {
        $result = array();
        $hasStates = $address->getCountry() ? $address->getCountry()->hasStates() : false;

        foreach (\XLite\Core\Database::getRepo('XLite\Model\AddressField')->findAllEnabled() as $field) {
            $method = 'get'
                . \Includes\Utils\Converter::convertToCamelCase(
                    $field->getViewGetterName() ?: $field->getServiceName()
                    );
                $addressFieldValue = $address->{$method}();

                switch ($field->getServiceName()) {
                    case 'state_id':
                        $addressFieldValue = $hasStates ? $addressFieldValue : null;
                        if (null === $addressFieldValue && $hasStates) {
                            $addressFieldValue = $address->getCustomState();
                        }
                        break;

                    case 'custom_state':
                        $addressFieldValue = $hasStates ? null : $address->getCustomState();
                        break;
                    default:
                }

                if (strlen($addressFieldValue) || $showEmpty) {
                    $result[$field->getServiceName()] = array(
                        'title'     => $field->getName(),
                        'value'     => $addressFieldValue
                    );
                }
        }

        return $result;
    }
    /**
     * The setting widget
     *
     * {@inheritdoc}
     *
     * @see \XLite\Model\Payment\Base\Processor::getSettingsWidget()
     */
    public function getSettingsWidget()
    {
        return 'modules/Digiwallet/Payment/Afterpay.twig';
    }

    /**
     * Get payment method row checkout template
     *
     * @param \XLite\Model\Payment\Method $method Payment method
     *
     * @return string
     */
    public function getCheckoutTemplate(\XLite\Model\Payment\Method $method)
    {
        return 'modules/Digiwallet/Payment/checkout/Afterpay.twig';
    }
    /**
     * return transaction process
     *
     * {@inheritdoc}
     *
     * @see \XLite\Model\Payment\Base\Online::processReturn()
     */
    public function processReturn(\XLite\Model\Payment\Transaction $transaction)
    {
        parent::processReturn($transaction);
        $this->processPayment($transaction, false);
    }

    /**
     * Callback message
     *
     * {@inheritdoc}
     *
     * @see \XLite\Model\Payment\Base\Online::processCallback()
     */
    public function processCallback(\XLite\Model\Payment\Transaction $transaction)
    {
        parent::processCallback($transaction);
        $this->processPayment($transaction, true);
    }

    /***
     * Get invoiceID for Afterpay only
     * @param unknown $request
     * @return unknown
     */
    private function getInvoiceID($request)
    {
        $invoiceID = $request->trxid;
        if(empty($invoiceID)) {
            $invoiceID = $request->invoiceID;
        }
        return $invoiceID;
    }
    /**
     * Process Afterpay result
     *
     * @param \XLite\Model\Payment\Transaction $transaction
     * @param unknown $callback
     */
    public function processPayment(\XLite\Model\Payment\Transaction $transaction, $callback = false)
    {
        $request = \XLite\Core\Request::getInstance();
        if ($callback && $request->isGet()) {
            \XLite\Core\TopMessage::addError('The callback method must be POST');
            header("Location:" . \XLite\Core\Converter::buildURL("checkout"));
            exit(0);
        }
        $isTest = $this->isTestMode($transaction->getPaymentMethod());
        $invoiceId = $this->getInvoiceID($request);

        if ($request->cancel){
            $this->setDetail('status', 'Customer has canceled checkout before completing their payments', 'Status');
            $transaction->setNote('Customer has canceled checkout before completing their payments');
            // Update transaction status
            $transaction->setStatus($transaction::STATUS_CANCELED);
            $transaction->getOrder()->setPaymentStatus(\XLite\Model\Order\Status\Payment::STATUS_CANCELED);
            if (!empty($invoiceId)) {
                $sale = (new \XLite\Module\Digiwallet\Payment\Model\TargetPaySale())->findByTargetPayId($invoiceId);
                if ($sale != null){
                    $sale->status = $transaction::STATUS_CANCELED;
                    \XLite\Core\Database::getRepo('\XLite\Module\Digiwallet\Payment\Model\TargetPaySale')->update($sale);
                    // Commit the transaction
                    \XLite\Core\Database::getEM()->flush();
                }
            }
            if($callback) {
                echo "Customer has canceled checkout before completing their payments";
            } else {
                header("Location:" . \XLite\Core\Converter::buildURL("checkout"));
            }
            exit(0);
        }
        // Process order status
        // Return from shop
        if(!empty($request->sid)){
            $sale = (new \XLite\Module\Digiwallet\Payment\Model\TargetPaySale())->findById($request->sid);
            // Return URL
            if(!empty($sale))
            {
                if(!empty($sale->paid)){
                    $url = \XLite\Core\Converter::buildURL(
                        'checkoutSuccess',
                        '',
                        array(
                            'order_number'  => $transaction->getOrder()->getOrderNumber(),
                            'payment' => 'bankwire'
                        )
                    );
                    header("Location:" . $url);
                    exit(0);
                } elseif (!empty($sale->more)){
                    list ($trxid, $status) = explode("|", $sale->more);
                    $sale->digi_txid = $trxid;
                    $sale->status = $transaction::STATUS_INPROGRESS;
                    \XLite\Core\Database::getRepo('\XLite\Module\Digiwallet\Payment\Model\TargetPaySale')->update($sale);

                    if (strtolower($status) != "captured") {
                        list ($trxid, $status, $ext_info) = explode("|", $sale->more);
                        if (strtolower($status) == "rejected") {
                            $this->reject_error = $ext_info;
                            // Show error message to customer page
                            $errors = new AfterpayValidationException(json_decode($this->reject_error, true));
                            $error_msg = $this->reject_error;
                            if($errors->IsValidationError()){
                                $error_msg = "";
                                foreach ($errors->getErrorItems() as $message) {
                                    $error_msg .= "<br/>";
                                    $error_msg .= (is_array($message)) ? implode(", ", $message) : $message;
                                }
                            }
                            \XLite\Core\TopMessage::addError("The order has been rejected with the reason: " . $error_msg);
                        } else {
                            $this->enrichment_url = $ext_info;
                            // Redirect to enrichment page
                            header("Location:" . $this->enrichment_url);
                            exit(0);
                        }
                    } else {
                        $this->initTargetPayment();
                        // Order captured. Transfer to return Url to check the payment status
                        $return_url = $this->digiCore->getReturnUrl() . '&trxid=' . $trxid;
                        header("Location:" . $return_url);
                        exit(0);
                    }
                }
            }
        }
        // Check return from Digiwallet
        if (!empty($invoiceId)){
            $sale = (new \XLite\Module\Digiwallet\Payment\Model\TargetPaySale())->findByTargetPayId($invoiceId);
            if ($sale != null){
                $this->initTargetPayment();
                $result = @$this->digiCore->checkPayment($invoiceId);
                $paymentStatus = false;
                $result_code = substr($result, 0, 6);
                if($result_code == "000000"){
                    $result = substr($result, 7);
                    list ($invoiceKey, $invoicePaymentReference, $status) = explode("|", $result);
                    if (strtolower($status) == "captured") {
                        $paymentStatus = true;
                    } elseif (strtolower($status) == "incomplete") {
                        list ($invoiceKey, $invoicePaymentReference, $status, $this->enrichment_url) = explode("|", $result);
                        // Redirect to enrichment user if not callback
                        if(!$callback){
                            // Redirect to enrichment page
                            header("Location:" . $this->enrichment_url);
                            exit(0);
                        }
                    } elseif (strtolower($status) == "rejected") {
                        list ($invoiceKey, $invoicePaymentReference, $status, $reject_reason, $this->reject_error) = explode("|", $result);
                        // Show error if return
                        if(!$callback){
                            // Show error message to customer page
                            $errors = new AfterpayValidationException(json_decode($this->reject_error, true));
                            $error_msg = $this->reject_error;
                            if($errors->IsValidationError()){
                                $error_msg = "";
                                foreach ($errors->getErrorItems() as $message) {
                                    $error_msg .= "<br/>";
                                    $error_msg .= (is_array($message)) ? implode(", ", $message) : $message;
                                }
                            }
                            \XLite\Core\TopMessage::addError("The order has been rejected with the reason: " . $error_msg);
                        }
                    }
                }
                if($isTest) {
                    // Don't set payment finished if test mode is enabled
                    //$paymentStatus = true;
                }
                // Check payment status
                if ($paymentStatus) {
                    // Update payment method id
                    $sale->method_id = $transaction->getPaymentMethod()->getMethodId();
                    // Update local as paid
                    $sale->paid = new \DateTime("now");
                    $sale->status = $transaction::STATUS_SUCCESS;
                    \XLite\Core\Database::getRepo('\XLite\Module\Digiwallet\Payment\Model\TargetPaySale')->update($sale);
                    \XLite\Core\Database::getEM()->flush();
                    // Update transaction status
                    $transaction->setStatus($transaction::STATUS_SUCCESS);
                    // Update to mark transaction as Order
                    $transaction->getOrder()->markAsOrder();
                    // Update order tatus
                    $transaction->getOrder()->setPaymentStatusByTransaction($transaction);
                    $transaction->getOrder()->setPaymentStatus(\XLite\Model\Order\Status\Payment::STATUS_PAID);
                    // Commit the transaction
                    \XLite\Core\Database::getEM()->flush();
                } else {
                    // Update transaction status
                    $transaction->setStatus($transaction::STATUS_INPROGRESS);
                    if($callback) {
                        echo "Not paid";
                        exit(0);
                    }
                }
            } else {
                $error_msg = $result;
                $errors = new AfterpayValidationException($result);
                if($errors->IsValidationError()){
                    $error_msg = "";
                    foreach ($errors->getErrorItems() as $message) {
                        $error_msg .= "<br/>";
                        $error_msg .= (is_array($message)) ? implode(", ", $message) : $message;
                    }
                }
                \XLite\Core\TopMessage::addError($error_msg);
                if($callback) {
                    echo $error_msg;
                    exit(0);
                }
            }
        }
    }
}
