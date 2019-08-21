<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * @file Provides support for Digiwallet iDEAL, Mister Cash, Sofort Banking, Credit and Paysafe
*
* @author Yellow Melon B.V.
*         @url http://www.idealplugins.nl
*
*/
namespace XLite\Module\Digiwallet\Payment\Base;

use XLite\Module\Digiwallet\Payment\Base\TargetPayCore;
use XLite\Module\Digiwallet\Payment\Model\TargetPaySale;
use XLite\Module\Digiwallet\Payment\Model\AfterpayValidationException;

class TargetPayPlugin extends \XLite\Model\Payment\Base\WebBased
{

    protected $digiCore = null;

    protected $payMethod = null;

    protected $language = null;

    protected $bankId = null;

    protected $allow_nobank = false;

    protected $params = null;

    /**
     * Check if test mode is enabled
     *
     * {@inheritdoc}
     *
     * @see \XLite\Model\Payment\Base\Processor::isTestMode()
     */
    public function isTestMode(\XLite\Model\Payment\Method $method)
    {
        return $method->getSetting('mode') != 'live';
    }

    /**
     * Get payment method admin zone icon URL
     *
     * @param \XLite\Model\Payment\Method $method
     *            Payment method
     *
     * @return string
     */
    public function getAdminIconURL(\XLite\Model\Payment\Method $method)
    {
        return \XLite::getInstance()->getShopURL() . 'skins/admin/modules/Digiwallet/Payment/' . $this->payMethod . '.png';
    }


    /**
     * Get payment method admin zone icon URL
     *
     * @param \XLite\Model\Payment\Method $method
     *            Payment method
     *
     * @return string
     */
    public function getStoreIconURL(\XLite\Model\Payment\Method $method)
    {
        return \XLite::getInstance()->getShopURL() . 'skins/customer/modules/Digiwallet/Payment/checkout/' . $this->payMethod . '.png';
    }

    /**
     * Check payment is configured or not
     *
     * {@inheritdoc}
     *
     * @see \XLite\Model\Payment\Base\Processor::isConfigured()
     */
    public function isConfigured(\XLite\Model\Payment\Method $method)
    {
        return parent::isConfigured($method) && $method->getSetting('rtlo');
    }

    /**
     * Init the targetPayment
     *
     * @return \TargetPayCore
     */
    protected function initTargetPayment()
    {
        if ($this->digiCore != null) {
            return $this->digiCore;
        }
        $pay_amount = round(100 * $this->transaction->getCurrency()->roundValue($this->transaction->getValue()));

        $this->digiCore = new TargetPayCore($this->payMethod, $this->getRTLO(), $this->language, $this->isTestMode($this->transaction->getPaymentMethod()));
        $this->digiCore->setBankId($this->bankId);
        $this->digiCore->setAmount($pay_amount);
        $this->digiCore->setCancelUrl($this->getReturnURL(null, true, true));
        $this->digiCore->setReturnUrl($this->getReturnURL(null, true));
        $this->digiCore->setReportUrl($this->getCallbackURL(null, true));
        $this->digiCore->setDescription($this->getTransactionDescription());
        return $this->digiCore;
    }

    /**
     * Check if the setting is OK
     *
     * {@inheritdoc}
     *
     * @see \XLite\Model\Payment\Base\Processor::isApplicable()
     */
    public function isApplicable(\XLite\Model\Order $order, \XLite\Model\Payment\Method $method)
    {
        // Check method
        if (empty($this->payMethod)) {
            return false;
        }
        return true;
    }

    /**
     * Get RTLO setting
     */
    protected function getRTLO()
    {
        if (! empty($this->rtlo)) {
            return $this->rtlo;
        }

        $method = $this->transaction->getPaymentMethod();
        $this->rtlo = $method->getSetting('rtlo');
        return $this->rtlo;
    }

    /**
     * get the description of payment
     */
    protected function getTransactionDescription()
    {
        return "Order #" . $this->getOrder()->order_id;
    }

    /**
     * Get the client host name
     *
     * @return unknown
     */
    protected function getClientHost()
    {
        return $_SERVER["HTTP_HOST"];
    }

    /**
     * The payment URL
     *
     * {@inheritdoc}
     *
     * @see \XLite\Model\Payment\Base\WebBased::getFormURL()
     */
    protected function getFormURL()
    {
        $this->initTargetPayment();
        // Check the payment URL
        if (! empty($this->digiCore->getBankUrl())) {
            $this->doRedirect($this->digiCore->getBankUrl());
            exit(0);
        }

        if(!empty($this->getOrder()->getProfile()) && !empty($this->getOrder()->getProfile()->getLogin())) {
            $this->digiCore->bindParam('email', $this->getOrder()->getProfile()->getLogin());
        }
        $this->digiCore->bindParam('userip', $_SERVER["REMOTE_ADDR"]);
        if(!empty($this->params)){
            foreach ($this->params as $key => $val){
                $this->digiCore->bindParam($key, $val);
            }
        }
        // Check to add CountryID for Sofort
        if($this->payMethod == "DEB") {
            if(empty($this->digiCore->getCountryId())) {
                // Get billing country code
                foreach ($this->getOrder()->getAddresses() as $add){
                    /** @var \XLite\Model\Address $add */
                    if($add->getIsBilling() && !empty($add->getCountry())) {
                        $this->digiCore->setCountryId(strtolower($add->getCountry()->getCode()));
                    }
                }
            }
        }
        // init transaction from Digiwallet before redirect to bank
        $result = @$this->digiCore->startPayment($this->allow_nobank);
        if ($result) {
            // Insert order to targetpay sale report
            $sale = new TargetPaySale();
            $sale->order_id = $this->getOrder()->order_id;
            $sale->method = $this->digiCore->getPayMethod();
            $sale->amount = $this->digiCore->getAmount();
            $sale->status = \XLite\Model\Payment\Transaction::STATUS_INITIALIZED;
            $sale->digi_txid = $this->digiCore->getTransactionId();
            $sale->more = $this->digiCore->getMoreInformation();
            $sale->digi_response = $this->digiCore->getTargetResponse();
            // $sale->paid = new \DateTime("now");
            \XLite\Core\Database::getRepo('\XLite\Module\Digiwallet\Payment\Model\TargetPaySale')->insert($sale);
            // Set order status
            $order = $this->getOrder();
            $order->setShippingStatus(\XLite\Model\Order\Status\Shipping::STATUS_NEW);
            $order->setOrderNumber(\XLite\Core\Database::getRepo('XLite\Model\Order')->findNextOrderNumber());
            \XLite\Core\Database::getEM()->persist($order);
            \XLite\Core\Database::getEM()->flush();
            // Return the URL
            if(is_string($result)){
                $this->doRedirect($this->digiCore->getBankUrl());
                exit(0);
            }
            // For BW, AFP results
            // Redirect to return URL to check result
            $sale_return_url = $this->digiCore->getReturnUrl() . "&sid=" . $sale->id;
            //return $sale_return_url;
            $this->doRedirect($sale_return_url);
            exit(0);
        }
        // Show error message
        $error_msg = $this->digiCore->getErrorMessage();
        // Check for afterpay error message
        if($this->digiCore->getPayMethod() == "AFP"){
            // Check exception for Afterpay
            $exception = new AfterpayValidationException($this->digiCore->getErrorMessage());
            if ($exception->IsValidationError()) {
                $error_msg = "";
                foreach ($exception->getErrorItems() as $key => $value) {
                    $error_msg .= (is_array($value)) ? implode(", ", $value) : $value;
                    $error_msg .= "<br/>";
                }
            }
        }
        \XLite\Core\TopMessage::addError($error_msg);
        return \XLite\Core\Converter::buildURL("checkout");
    }

    /**
     * validate and remove dirty tag
     *
     * @param unknown $location
     * @return unknown
     */
    protected function validateBankUrl($location)
    {
        $location = preg_replace('/[\x00-\x1f].*$/sm', '', $location);
        $location = str_replace(array(
            '"',
            "'",
            '<',
            '>'
        ), array(
            '&quot;',
            '&#039;',
            '&lt;',
            '&gt;'
        ), $this->convertAmp($location));
        return $location;
    }

    /**
     * validate and remove dirty tag
     *
     * @param unknown $str
     * @return unknown
     */
    protected function convertAmp($str)
    {
        // Do not convert html entities like &thetasym; &Omicron; &euro; &#8364; &#8218;
        return preg_replace('/&(?![a-zA-Z0-9#]{1,8};)/Ss', '&amp;', $str);
    }

    /**
     * Don't pass parame to form
     *
     * {@inheritdoc}
     *
     * @see \XLite\Model\Payment\Base\WebBased::getFormFields()
     */
    protected function getFormFields()
    {
        return [
            'paymethod' => $this->payMethod
        ];
    }

    /**
     * Process return data
     *
     * @param \XLite\Model\Payment\Transaction $transaction
     * @param unknown $is_callback
     */
    protected function handlePaymentResult(\XLite\Model\Payment\Transaction $transaction, $is_callback = false)
    {
        $request = \XLite\Core\Request::getInstance();
        // Check if callback method is not POST
        if ($is_callback && $request->isGet()) {
            \XLite\Core\TopMessage::addError('The callback method must be POST');
            return;
        }
        $isTest = $this->isTestMode($transaction->getPaymentMethod());
        $trxid = $request->trxid;
        if($this->payMethod == "PYP") {
            // Papal method
            if($is_callback) {
                // Report URL
                $trxid = $request->acquirerID;
            } else {
                // Return/Cancel URL
                $trxid = $request->paypalid;
            }
        }
        if(!isset($trxid) || empty($trxid)){
            echo "No transaction found!";
            die;
        } else {
            // Check the local transaction
            $sale = (new \XLite\Module\Digiwallet\Payment\Model\TargetPaySale())->findByTargetPayId($trxid);
            if ($sale == null || empty($sale)) {
                \XLite\Core\TopMessage::addError('No entry found with Digiwallet id: ' . htmlspecialchars($trxid));
                return;
            }
            // Check current status of transaction
            if($transaction->getStatus() === $transaction::STATUS_SUCCESS){
                \XLite\Core\TopMessage::addInfo('Your transaction had been processed!');
                return;
            }
            // Check payment with Digiwallet
            $this->initTargetPayment();
            // Alway return true if testmode is enabled
            $paid = @$this->digiCore->checkPayment($trxid); //$isTest || @$this->digiCore->checkPayment($trxid);
            if ($paid) {
                $status = $transaction::STATUS_SUCCESS;
                // Update local as paid
                $sale->paid = new \DateTime("now");
                if($is_callback) {
                    echo "Order placed.";
                }
            }
            elseif ($is_callback) {
                $status = $transaction::STATUS_INPROGRESS;
                echo $this->digiCore->getErrorMessage();
            }
            else {
                $status = $transaction::STATUS_PENDING;
                $this->markCallbackRequestAsInvalid($this->digiCore->getErrorMessage());
                \XLite\Core\TopMessage::addError($this->digiCore->getErrorMessage());
                // Set log history
                $this->setDetail('status', $this->digiCore->getErrorMessage(), 'Status');
                $transaction->setNote($this->digiCore->getErrorMessage());
            }
            // Update payment method id
            $sale->method_id = $transaction->getPaymentMethod()->getMethodId();
            // Update targetpay sale report
            $sale->status = $status;
            \XLite\Core\Database::getRepo('\XLite\Module\Digiwallet\Payment\Model\TargetPaySale')->update($sale);
            // Commit the transaction
            \XLite\Core\Database::getEM()->flush();
            // Update the order if transaction success
            if ($paid) {
                // Update the order status and change to Order in cases of reportUrl is called.
                $transaction->setStatus($status);
                // Commit the transaction
                \XLite\Core\Database::getEM()->flush();
                // Update to mark transaction as Order
                $transaction->getOrder()->markAsOrder();
                // Update order tatus
                $transaction->getOrder()->setPaymentStatusByTransaction($transaction);
                $transaction->getOrder()->setPaymentStatus(\XLite\Model\Order\Status\Payment::STATUS_PAID);
            }
            // Commit the transaction
            \XLite\Core\Database::getEM()->flush();
            // Validate redirections
            if($is_callback) {
                // Print message and finish
                die;
            }
            elseif(!$paid) {
                // Show checkout error message
                $this->doRedirect(\XLite\Core\Converter::buildURL("checkout"));
                exit(0);
            }
        }
    }

    /**
     * Force redirect URL to specified URL
     * @param $url
     */
    function doRedirect($url)
    {
        \XLite\Core\TopMessage::getInstance()->clearAJAX();
        header('ajax-response-status: 278');
        if(\XLite\Core\Request::getInstance()->isAJAX()) {
            \XLite\Core\Event::getInstance()->display();
            \XLite\Core\Event::getInstance()->clear();
        }
        if (\XLite::getCleanUpCacheFlag()) {
            $url .= (strpos($url, '?') === false ? '?' : '&')
                . \Includes\Decorator\Utils\CacheManager::KEY_NAME . '='
                . \Includes\Decorator\Utils\CacheManager::getKey(true);
        }
        if (LC_USE_CLEAN_URLS
            && \XLite\Core\Router::getInstance()->isUseLanguageUrls()
            && !\XLite::isAdminZone()
        ) {
            $webDir = \Includes\Utils\ConfigParser::getOptions(['host_details', 'web_dir']);
            if ($webDir && strpos($url, $webDir) !== 0 && strpos($url, 'http') !== 0) {
                $url = $webDir . '/' . $url;
            }
        }

        \XLite\Core\Operator::redirect($url, true, 200);
    }
}
