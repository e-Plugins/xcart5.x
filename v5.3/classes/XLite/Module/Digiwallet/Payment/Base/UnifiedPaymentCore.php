<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * @file Provides support for Digiwallet iDEAL, Mister Cash, Sofort Banking, Credit and Paysafe
 *
 * @author Yellow Melon B.V.
 * @url http://www.idealplugins.nl
 *
 */

namespace XLite\Module\Digiwallet\Payment\Base;

use XLite\Module\Digiwallet\Payment\client\ClientCore;
use XLite\Module\Digiwallet\Payment\Model\TargetPaySale;

class UnifiedPaymentCore extends TargetPayPlugin
{

    /**
     * Start paymet here for EPS and GIP
     * @return string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getFormURL()
    {
        $consumerEmail = "";
        if (!empty($this->getOrder()->getProfile()) && !empty($this->getOrder()->getProfile()->getLogin())) {
            $consumerEmail = $this->getOrder()->getProfile()->getLogin();
        }
        $amount = round(100 * $this->transaction->getCurrency()->roundValue($this->transaction->getValue()));
        $isTest = $this->isTestMode($this->transaction->getPaymentMethod());
        $formData = array(
            'amount' => $amount,
            'inputAmount' => $amount,
            'consumerEmail' => $consumerEmail,
            'description' => $this->getTransactionDescription(),
            'returnUrl' => $this->getReturnURL(null, true),
            'reportUrl' => $this->getCallbackURL(null, true),
            'cancelUrl' => $this->getReturnURL(null, true, true),
            'test' => $isTest ? 1 : 0
        );
        $digiCore = new ClientCore($this->getRTLO(), $this->payMethod, $this->language);
        // init transaction from Digiwallet before redirect to bank
        /** @var \Digiwallet\Packages\Transaction\Client\Response\CreateTransaction $result */
        $result = $digiCore->createTransaction($this->getApiToken(), $formData);
        if ($result) {
            // Insert order to targetpay sale report
            $sale = new TargetPaySale();
            $sale->order_id = $this->getOrder()->order_id;
            $sale->method = $this->payMethod;
            $sale->amount = $amount;
            $sale->status = \XLite\Model\Payment\Transaction::STATUS_INITIALIZED;
            $sale->digi_txid = $result->transactionId();
            $sale->more = $result->transactionKey();
            $sale->digi_response = is_array($result->response()) ? json_encode($result->response()) : '';
            // $sale->paid = new \DateTime("now");
            \XLite\Core\Database::getRepo('\XLite\Module\Digiwallet\Payment\Model\TargetPaySale')->insert($sale);
            // Set order status
            $order = $this->getOrder();
            $order->setShippingStatus(\XLite\Model\Order\Status\Shipping::STATUS_NEW);
            $order->setPaymentStatus(\XLite\Model\Order\Status\Payment::STATUS_QUEUED);
            //$order->setOrderNumber(\XLite\Core\Database::getRepo('XLite\Model\Order')->findNextOrderNumber());
            //$order->markAsOrder();
            //\XLite\Core\Mailer::sendOrderCreated($this->getOrder(), false);

            \XLite\Core\Database::getEM()->persist($order);
            \XLite\Core\Database::getEM()->flush();
            // Return the URL
            $this->doRedirect($result->launchUrl());
            exit(0);
        }
        // Show error message
        \XLite\Core\TopMessage::addError($digiCore->getErrorMessage());
        return \XLite\Core\Converter::buildURL("checkout");
    }

    /**
     * Process return data
     *
     * @param \XLite\Model\Payment\Transaction $transaction
     * @param bool $is_callback
     * @throws \GuzzleHttp\Exception\GuzzleException
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
        if(empty($trxid)) {
            $trxid = $request->transactionID;
        }
        if (!isset($trxid) || empty($trxid)) {
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
            if ($transaction->getStatus() === $transaction::STATUS_SUCCESS) {
                \XLite\Core\TopMessage::addInfo('Your transaction had been processed!');
                return;
            }
            $digiCore = new ClientCore($this->getRTLO(), $this->payMethod, $this->language);
            // Alway return true if testmode is enabled
            $paid = $digiCore->checkTransaction($this->getApiToken(), $trxid);
            if ($paid || $isTest) {
                $status = $transaction::STATUS_SUCCESS;
                // Update local as paid
                $sale->paid = new \DateTime("now");
                if ($is_callback) {
                    echo "Order placed.";
                }
            } elseif ($is_callback) {
                $status = $transaction::STATUS_INPROGRESS;
                echo $digiCore->getErrorMessage();
            } else {
                $status = $transaction::STATUS_PENDING;
                $this->markCallbackRequestAsInvalid($digiCore->getErrorMessage());
                \XLite\Core\TopMessage::addError($digiCore->getErrorMessage());
                // Set log history
                $this->setDetail('status', $digiCore->getErrorMessage(), 'Status');
                $transaction->setNote($digiCore->getErrorMessage());
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
                // Send Mail
                \XLite\Core\Mailer::sendOrderCreated($transaction->getOrder(), false);
                // Update order tatus
                $transaction->getOrder()->setPaymentStatusByTransaction($transaction);
                if (!$transaction->getOrder()->isNotificationSent()) {
                    \XLite\Core\Mailer::sendOrderProcessed($transaction->getOrder(), false);
                    $transaction->getOrder()->setIsNotificationSent(true);
                }
                $transaction->getOrder()->setPaymentStatus(\XLite\Model\Order\Status\Payment::STATUS_PAID);
            }
            // Commit the transaction
            \XLite\Core\Database::getEM()->flush();
            // Validate redirections
            if ($is_callback) {
                // Print message and finish
                die;
            } elseif (!$paid) {
                // Show checkout error message
                $this->doRedirect(\XLite\Core\Converter::buildURL("checkout"));
                exit(0);
            }
        }
    }
}