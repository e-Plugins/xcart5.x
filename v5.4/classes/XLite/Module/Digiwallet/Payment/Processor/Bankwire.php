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

class Bankwire extends TargetPayPlugin
{
    /**
     * The confirmation message after returning from Target page
     */
    private $confirmation_message = "";
    /**
     * The contructor
     */
    public function __construct()
    {
        $this->payMethod = "BW";
        $this->currency = "EUR";
        $this->language = 'nl';
        $this->allow_nobank = true;
        // Params to add to Url
        $this->params = ['salt' => 'e381277'];
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
        return 'modules/Digiwallet/Payment/Bankwire.twig';
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
        return 'modules/Digiwallet/Payment/checkout/Bankwire.twig';
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
        // http://xcart.local/cart.php?target=payment_return&txn_id_name=txnId&txnId=000017-UPFR&sid=9
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

    /**
     * Process to redirect to other controller
     * {@inheritDoc}
     * @see \XLite\Model\Payment\Base\WebBased::doCustomReturnRedirect()
     */
    public function doCustomReturnRedirect()
    {
        $request = \XLite\Core\Request::getInstance();
        if(!empty($this->confirmation_message)){
            if(!empty($request->show_warning)){
                \XLite\Core\TopMessage::addRawWarning($this->confirmation_message);
            }else{
                echo $this->printConfirmInformation();
            }
        } else {
            $url = \XLite\Core\Converter::buildURL(
                'checkoutSuccess',
                '',
                array(
                    'order_number'  => $this->getOrder()->getOrderNumber(),
                    'payment' => 'bankwire'
                )
                );
            $this->doRedirect($url);
            exit(0);
        }
    }
    /**
     * use custom to redirect to another pay
     * {@inheritDoc}
     * @see \XLite\Model\Payment\Base\Online::getReturnType()
     */
    public function getReturnType()
    {
        $request = \XLite\Core\Request::getInstance();
        if(!empty($request->sid)){
            $sale = (new \XLite\Module\Digiwallet\Payment\Model\TargetPaySale())->findById($request->sid);
            if(!empty($sale)){
                return \XLite\Model\Payment\Base\WebBased::RETURN_TYPE_CUSTOM;
            }
        }
        return null;
    }

    /**
     * Process callback payment
     *
     * @param \XLite\Model\Payment\Transaction $transaction
     * @param unknown $callback
     */
    private function processPayment($transaction, $callback = false)
    {
        $request = \XLite\Core\Request::getInstance();
        //var_dump($request); die;
        // Check post method for callback
        if ($callback && $request->isGet()) {
            \XLite\Core\TopMessage::addError('The callback method must be POST');
            $this->doRedirect(\XLite\Core\Converter::buildURL("checkout"));
            exit(0);
        }
        $isTest = $this->isTestMode($transaction->getPaymentMethod());

        if ($request->cancel){
            $this->setDetail('status', 'Customer has canceled checkout before completing their payments', 'Status');
            $transaction->setNote('Customer has canceled checkout before completing their payments');
            // Update transaction status
            $transaction->setStatus($transaction::STATUS_CANCELED);
            $transaction->getOrder()->setPaymentStatus($transaction::STATUS_CANCELED);
            if (!empty($request->trxid)) {
                $sale = (new \XLite\Module\Digiwallet\Payment\Model\TargetPaySale())->findByTargetPayId($request->trxid);
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
                $this->doRedirect(\XLite\Core\Converter::buildURL("checkout"));
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
                    $this->doRedirect($url);
                    exit(0);
                } elseif (!empty($sale->more)){
                    list($trxid, $accountNumber, $iban, $bic, $beneficiary, $bank) = explode("|", $sale->more);
                    $sale->digi_txid = $trxid;
                    $sale->status = $transaction::STATUS_INPROGRESS;
                    \XLite\Core\Database::getRepo('\XLite\Module\Digiwallet\Payment\Model\TargetPaySale')->update($sale);
                    $message = $this->getResultMessage($sale, $transaction);
                    // Show message to inform user
                    //\XLite\Core\TopMessage::addRawWarning($message);
                    $this->confirmation_message = $message;
                }
                // For Bankwire, making transaction as success
                $transaction->setStatus($transaction::STATUS_SUCCESS);
                // Commit the transaction
                \XLite\Core\Database::getEM()->flush();
                // Update the order if transaction success
                $transaction->getOrder()->markAsOrder();
                \XLite\Core\Database::getEM()->flush();
            }
        }
        $status_paid = false;
        // Check return from Digiwallet
        if (!empty($request->trxid)){
            $sale = (new \XLite\Module\Digiwallet\Payment\Model\TargetPaySale())->findByTargetPayId($request->trxid);
            if ($sale != null){
                $this->initTargetPayment();
                $paymentIsPartial = false;
                $bw_paid_amount = 0;
                // Alway return true if testmode is enabled
                /*
                if($isTest) {
                    $paid = true;
                } else {
                    $paid = @$this->digiCore->checkPayment($request->trxid);
                    if($paid) {
                        $consumber_info = $this->digiCore->getConsumerInfo();
                        if (!empty($consumber_info) && $consumber_info['bw_paid_amount'] > 0) {
                            if ($consumber_info['bw_paid_amount'] < $consumber_info['bw_due_amount']) {
                                $paymentIsPartial = true;
                                $bw_paid_amount = $consumber_info['bw_paid_amount'];
                            }
                        }
                    }
                }
                */
                $paid = @$this->digiCore->checkPayment($request->trxid);
                if($paid) {
                    $consumber_info = $this->digiCore->getConsumerInfo();
                    if (!empty($consumber_info) && $consumber_info['bw_due_amount'] > 0) {
                        if ($consumber_info['bw_paid_amount'] < $consumber_info['bw_due_amount']) {
                            $paymentIsPartial = true;
                            $bw_paid_amount = $consumber_info['bw_paid_amount'];
                        }
                    }
                }
                $status_paid = $paid;
                // For Bankwire, making transaction as success
                $transaction->setStatus($transaction::STATUS_SUCCESS);
                $transaction->getOrder()->markAsOrder();
                // Commit the transaction
                \XLite\Core\Database::getEM()->flush();
                if ($paid) {
                    // Update payment method id
                    $sale->method_id = $transaction->getPaymentMethod()->getMethodId();
                    // Update local as paid
                    $sale->paid = new \DateTime("now");
                    $sale->status = $transaction::STATUS_SUCCESS;
                    \XLite\Core\Database::getRepo('\XLite\Module\Digiwallet\Payment\Model\TargetPaySale')->update($sale);
                    \XLite\Core\Database::getEM()->flush();
                    // Update the order if transaction success
                    if($paymentIsPartial) {
                        $bt = $transaction->createBackendTransaction(\XLite\Model\Payment\BackendTransaction::TRAN_TYPE_SALE);
                        $bt->setValue($bw_paid_amount/100);
                        $bt->setStatus($transaction::STATUS_SUCCESS);
                        \XLite\Core\Database::getEM()->flush();
                        $transaction->getOrder()->setPaymentStatus($transaction->getOrder()->getCalculatedPaymentStatus(true));
                        \XLite\Core\Database::getEM()->flush();
                        if($callback) {
                            echo "Partially paid.";
                            die;
                        }
                    } else {
                        $transaction->getOrder()->setPaymentStatus(\XLite\Model\Order\Status\Payment::STATUS_PAID);
                        \XLite\Core\Database::getEM()->flush();
                        if($callback) {
                            echo "Fully paid.";
                            die;
                        }
                    }
                } 
            }
        }
        if(!$status_paid && $callback) {
            echo "Not paid";
            exit(0);
        }
    }

    /**
     * Get the result message
     *
     * @param \XLite\Model\Payment\Transaction $transaction
     * @param \XLite\Module\Digiwallet\Payment\Model\TargetPaySale $sale
     */
    private function getResultMessage($sale, $transaction)
    {
        if(!empty($sale->more)){
            list($trxid, $accountNumber, $iban, $bic, $beneficiary, $bank) = explode("|", $sale->more);
            $total_amount = $transaction->getOrder()->getTotal();
            // Encode email address
            $emails = str_split($transaction->getOrder()->getProfile()->getLogin());
            $counter = 0;
            $customer_email = "";
            foreach ($emails as $char) {
                if($counter == 0) {
                    $customer_email .= $char;
                    $counter++;
                } else if($char == "@") {
                    $customer_email .= $char;
                    $counter++;
                } else if($char == "." && $counter > 1) {
                    $customer_email .= $char;
                    $counter++;
                } else if($counter > 2) {
                    $customer_email .= $char;
                } else {
                    $customer_email .= "*";
                }
            }
            $formatter_en = <<<HTML
<div class="bankwire-info" style = "padding: 50px; line-height:1.5em;">
    <h4 style="padding-bottom: 20px; font-weight: bold; font-size: 150%%;">Thank you for ordering in our webshop!</h4>
    <p>
        You will receive your order as soon as we receive payment from the bank. <br>
        Would you be so friendly to transfer the total amount of %s to the bankaccount <b> %s </b> in name of %s* ?
    </p>
    <p>
        State the payment feature <b>%s</b>, this way the payment can be automatically processed.<br>
        As soon as this happens you shall receive a confirmation mail on %s
    </p>
    <p>
        If it is necessary for payments abroad, then the BIC code from the bank %s and the name of the bank is %s.
    <p>
        <i>* Payment for our webstore is processed by TargetMedia. TargetMedia is certified as a Collecting Payment Service Provider by Currence. This means we set the highest security standards when is comes to security of payment for you as a customer and us as a webshop.</i>
    </p>
</div>
HTML;
            $formatter_nl = <<<HTML
<div class="bankwire-info" style = "padding: 50px; line-height:1.5em;">
    <h4 style="padding-bottom: 20px; font-weight: bold; font-size: 150%%;">Bedankt voor uw bestelling in onze webwinkel!</h4>
    <p>
        U ontvangt uw bestelling zodra we de betaling per bank ontvangen hebben. <br>
        Zou u zo vriendelijk willen zijn het totaalbedrag van %s over te maken op bankrekening <b> %s </b> t.n.v. %s* ?
    </p>
    <p>
        Vermeld daarbij als betaalkenmerk <b>%s</b>, zodat de betaling automatisch verwerkt kan worden.
        Zodra dit gebeurd is ontvangt u een mail op %s ter bevestiging.
    </p>
    <p>
        Mocht het nodig zijn voor betalingen vanuit het buitenland, dan is de BIC code van de bank %s en de naam van de bank is '%s'.
        Zorg ervoor dat u kiest voor kosten in het buitenland voor eigen rekening (optie: OUR), anders zal het bedrag wat binnenkomt te laag zijn.
    <p>
        <i>* De betalingen voor onze webwinkel worden verwerkt door TargetMedia. TargetMedia is gecertificeerd als Collecting Payment Service Provider door Currence.
        Dat houdt in dat zij aan strenge eisen dient te voldoen als het gaat om de veiligheid van de betalingen voor jou als klant en ons als webwinkel.</i>
    </p>
</div>
HTML;
            $language_key_name = "digiwallet_payment_bankwire_success_message";
            $this->checkLanguage($language_key_name, array('en' => $formatter_en, 'nl' => $formatter_nl));
            return sprintf(static::t($language_key_name), \XLite\View\AView::formatPrice($total_amount, $transaction->getOrder()->getCurrency()), $iban, $beneficiary, $trxid, $customer_email, $bic, $bank);
        }
        return null;
    }

    /***
     * Update to add language code for transaltion
     * @param unknown $lbl_name
     * @param array $translations
     */
    private function checkLanguage($lbl_name, $translations = array('en' => '', 'nl' => ''))
    {
        if(!\XLite\Core\Database::getRepo('\XLite\Model\LanguageLabel')->findOneByName($lbl_name)){
            // Label not found
            $lbl = new \XLite\Model\LanguageLabel();
            $lbl->setName($lbl_name);
            $lbl = \XLite\Core\Database::getRepo('\XLite\Model\LanguageLabel')->insert($lbl);
            $objects = array();
            // Add translation
            foreach ($translations as $code => $message){
                $translation = $lbl->getTranslation($code);
                $translation->setLabel($message);
                $objects[] = $translation;
            }
            // Reset language to load new data
            \XLite\Core\Translation::getInstance()->reset();
            //Update to language translation
            \XLite\Core\Database::getRepo('\XLite\Model\LanguageLabel')->insertInBatch($objects);
        }
    }

    /**
     * Print confirm information to existing html
     */
    private function printConfirmInformation()
    {
        return <<<HTML
<div style="display: none;" id="bank-wire-information"> $this->confirmation_message </div>
<script>
    window.onload = function()
    {
        var main =     document.getElementById("main")
                    || document.getElementById("main-wrapper")
                    || document.getElementById("page")
                    || document.getElementById("page-wrapper")
                    || document.body;
        if(main){
            main.innerHTML = document.getElementById("bank-wire-information").innerHTML;
        } else {
            window.location.href = window.location.href + "&show_warning=true"
        }
    };
</script>
HTML;
    }
}
