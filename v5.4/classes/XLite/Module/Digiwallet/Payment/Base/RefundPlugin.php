<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * Copyright (c) 2011-present Qualiteam software Ltd. All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */

namespace XLite\Module\Digiwallet\Payment\Base;

use XLite\Module\Digiwallet\Payment\Base\TargetPayCore;
use XLite\Module\Digiwallet\Payment\Model\TargetPaySale;
use XLite\Module\Digiwallet\Payment\Model\TargetPayRefund;
/**
 * Digiwallet refunding function
 */
class RefundPlugin extends \XLite\View\AView
{
    /**
     * Default Language for Digiwallet
     * @var string
     */
    protected $language = "nl";
    /**
     * Widget parameters
     */
    const PARAM_ORDER = 'order.operations';

    /**
     * Get order
     *
     * @return integer
     */
    public function getOrderId()
    {
        return $this->getOrder()->getOrderId();
    }

    /**
     * Return default template
     *
     * @return string
     */
    protected function getDefaultTemplate()
    {
        return 'modules/Digiwallet/Payment/RefundView.twig';
    }

    /**
     * Check widget visibility
     *
     * @return boolean
     */
    protected function isVisible()
    {
        // Check if order belong to Digiwallet and success
        $transaction = (new \XLite\Module\Digiwallet\Payment\Model\TargetPaySale())->getSuccessTransaction($this->getOrder()->order_id);
        return ($transaction && $this->isOrderSuccess());
    }
    
    /**
     * Check if current Method support refund or not
     * @return boolean
     */
    protected function canRefund()
    {
        // Check Payment token setting
        /** @var \XLite\Model\Payment\Method $paymentMethod */
        $paymentMethod = $this->getPaymentMethod();
        if($paymentMethod) {
            return !empty($paymentMethod->getSetting("token")) && $this->getRemainRefundAmount() > 0;
        }
        return false;
    }
    /**
     * Do refund if any
     */
    protected function doRefundorDelete()
    {
        $request = \XLite\Core\Request::getInstance();
        $paymentMethod = $this->getPaymentMethod();
        $transaction_info = (new \XLite\Module\Digiwallet\Payment\Model\TargetPaySale())->getSuccessTransaction($this->getOrderId());
        if ($paymentMethod->getSetting("mode") != 'live') {
            $testmode = true;
        } else {
            $testmode = false;
        }
        if(!empty($request->action_type))
        {
            /** @var \XLite\Model\Payment\Method $paymentMethod */
            $paymentMethod = $this->getPaymentMethod();
            if($request->action_type == "refund"){
                $refund_message = $request->message;
                $refund_amount = $request->amount;
                if(empty($refund_message)) 
                {
                    \XLite\Core\TopMessage::addError(static::t("Refunding message can't be empty."));                    
                } 
                else if(empty($refund_amount) || $refund_amount == 0) 
                {
                    \XLite\Core\TopMessage::addError(static::t("Refunding amount must be a number."));
                } 
                else 
                {
                    if($this->getRemainRefundAmount() <= 0 || (int) (100 * ((float)$this->getRemainRefundAmount())) < (int) (100 * ((float) $refund_amount)))
                    {
                        \XLite\Core\TopMessage::addError(static::t("The amount is invalid."));
                    }
                    else 
                    {
                        // Do Refund
                        $internalNote =  "Refunding Order with orderNumber: " . $this->getOrderNumber() . " - Digiwallet transactionId: " . $transaction_info->digi_txid . " - Total price: " . $transaction_info->amount/100;
                        $consumerName = "GUEST";
                        if(!empty($this->getOrder()->getAddresses())) {
                            /** @var \XLite\Model\Address $add */
                            foreach ($this->getOrder()->getAddresses() as $add){
                                if($add->getIsBilling()) {
                                    $item = $this->getAddressSectionData($add);
                                    $consumerName = isset($item['firstname']) ? $item['firstname']['value'] : "";
                                    $consumerName .= isset($item['lastname']) ? " " . $item['lastname']['value'] : "";
                                }
                            }
                        }
                        // Build refund data
                        $refundData = array(
                            'paymethodID' => $transaction_info->method,
                            'transactionID' => $transaction_info->digi_txid,
                            'amount' => (int) ($refund_amount * 100), // Parse amount to Int and convert to cent value
                            'description' => $refund_message,
                            'internalNote' => $internalNote,
                            'consumerName' => $consumerName
                        );

                        $digiCore = new TargetPayCore($transaction_info->method, $paymentMethod->getSetting("rtlo"), $this->language, $testmode);
                        if(!$testmode && !$digiCore->refundInvoice($refundData, $paymentMethod->getSetting("token")))
                        {
                            \XLite\Core\TopMessage::addError("Digiwallet refunding error: {$digiCore->getRawErrorMessage()}");
                        }
                        else
                        {
                            \XLite\Core\TopMessage::addInfo("Refunding has been placed successfully.");
                            // Insert to history log
                            $refund = new TargetPayRefund();
                            $refund->refund_id = $testmode ? time() : $digiCore->getRefundID();
                            $refund->order_id = $this->getOrderId();
                            $refund->transaction_id = $transaction_info->digi_txid;
                            $refund->refund_amount = $refund_amount;
                            $refund->refund_message = $refund_message;
                            $refund->status = "success";
                            $refund->datetimestamp = new \DateTime("now");
                            \XLite\Core\Database::getEM()->refresh($this->getOrder());
                            \XLite\Core\Database::getEM()->flush();
                            \XLite\Core\Database::getRepo('\XLite\Module\Digiwallet\Payment\Model\TargetPayRefund')->insert($refund);
                            // Update payment status to Refunded
                            /** @var \XLite\Model\Payment\Transaction $transaction */
                            $transaction = $this->getTransaction();
                            // Add refund transaction
                            $bt = $transaction->createBackendTransaction(\XLite\Model\Payment\BackendTransaction::TRAN_TYPE_REFUND);
                            $bt->setStatus($transaction::STATUS_SUCCESS);
                            $bt->setValue($refund_amount);
                            // Setting Order status
                            $transaction->getOrder()->setOldPaymentStatus($transaction->getOrder()->getPaymentStatus());
                            $paymentStatus = $this->getRemainRefundAmount() > 0 ? \XLite\Model\Order\Status\Payment::STATUS_PART_PAID : \XLite\Model\Order\Status\Payment::STATUS_REFUNDED;
                            $transaction->getOrder()->setPaymentStatus($paymentStatus);
                            // Update Order status
                            \XLite\Core\Database::getEM()->flush();
                        }
                    }                    
                }
            }
            else if($request->action_type == "cancel-refund") 
            {
                $refund_id = $request->refund_id;
                if(!empty($refund_id))
                {
                    // UPDATE ORDER STATUS TO PAID
                    \XLite\Core\Database::getEM()->refresh($this->getOrder());
                    \XLite\Core\Database::getEM()->flush();
                    
                    $refund_history = (new \XLite\Module\Digiwallet\Payment\Model\TargetPayRefund())->findByRefundId($refund_id);
                    $digiCore = new TargetPayCore($transaction_info->method, $paymentMethod->getSetting("rtlo"), $this->language, $testmode);
                    if(!$testmode && !$digiCore->deleteRefund($refund_history->transaction_id, $paymentMethod->getSetting("token")))
                    {
                        \XLite\Core\TopMessage::addError("Digiwallet cancelling refund error: {$digiCore->getRawErrorMessage()}");
                    } 
                    else 
                    {
                        // UPDATE REFUND TABLE FOR TRACKING
                        $refund_history->status = 'cancelled';
                        $refund_history->last_modified = new \DateTime("now");
                        \XLite\Core\Database::getRepo('\XLite\Module\Digiwallet\Payment\Model\TargetPayRefund')->update($refund_history);
                        \XLite\Core\TopMessage::addInfo("Cancelling the refund has been updated successfully.");
                        // Check to update status of payment to paid or parially paid when cancelling refund
                        /** @var \XLite\Model\Payment\Transaction $transaction */
                        $transaction = $this->getTransaction();
                        // Add refund transaction
                        $transaction->setType(\XLite\Model\Payment\BackendTransaction::TRAN_TYPE_SALE);
                        $transaction->getOrder()->setOldPaymentStatus($transaction->getOrder()->getPaymentStatus());
                        $paymentStatus = $this->getRemainRefundAmount() == ($transaction_info->amount/100) ? \XLite\Model\Order\Status\Payment::STATUS_PAID : \XLite\Model\Order\Status\Payment::STATUS_PART_PAID;
                        $transaction->getOrder()->setPaymentStatus($paymentStatus);
                        // Update Order status
                        \XLite\Core\Database::getEM()->flush();                        
                    }
                }                
            }
            // Redirect to detail order page
            \XLite\Core\Operator::redirect($this->getOrderDetailUrl(), true);
        }
    }
    /**
     * 
     * @param unknown $orderStatus
     * @return boolean
     */
    protected function changeOrderStatus($orderStatus)
    {
        $cart = \XLite\Core\Database::getRepo('XLite\Model\Order')->find($this->getOrderId());
        if ($cart) {
            \XLite\Model\Cart::setObject($cart);
        } else {
            return false;
        }
        $cart->setPaymentStatus($orderStatus);
        \XLite\Core\Database::getEM()->flush();
        return true;
    }
    /**
     * Get refund history
     * 
     * @return \Doctrine\ORM\PersistentCollection|number
     */
    protected function getRefundHistory()
    {
        return (new \XLite\Module\Digiwallet\Payment\Model\TargetPayRefund())->findByOrderId($this->getOrderId());
    }
    /**
     * Get refund available amount
     * @return number
     */
    protected function getRemainRefundAmount()
    {
        $transaction_info = (new \XLite\Module\Digiwallet\Payment\Model\TargetPaySale())->getSuccessTransaction($this->getOrderId());
        $refund_histories = (new \XLite\Module\Digiwallet\Payment\Model\TargetPayRefund())->findByOrderId($this->getOrderId());
        $total_refunded = 0;
        if(!empty($refund_histories)) {
            foreach ($refund_histories as $refund_item) {
                if($refund_item->status == "success"){
                    $total_refunded += $refund_item->refund_amount;
                }
            }
        }
        return $transaction_info->amount/100 - $total_refunded;
    }
    /**
     * Get order transaction
     * 
     * @return \XLite\Model\Payment\Transaction|NULL
     */
    protected function getTransaction()
    {        
        /** @var \XLite\Model\Payment\Method $paymentMethod */
        $paymentMethod = $this->getPaymentMethod();
        $transactions = $this->getOrder()->getPaymentTransactions();
        foreach ($transactions as $transaction) {
            /** @var \XLite\Model\Payment\Transaction $transaction */
            if($transaction->isCompleted() && $transaction->getPaymentMethod()->getMethodId() == $paymentMethod->getMethodId()){
                return $transaction;
            }
        }
        return null;
    }
    /**
     * Get payment method of Order
     */
    protected function getPaymentMethod()
    {
        $transaction_info = (new \XLite\Module\Digiwallet\Payment\Model\TargetPaySale())->getSuccessTransaction($this->getOrderId());
        if($transaction_info) {
            return \XLite\Core\Database::getRepo('XLite\Model\Payment\Method')->find($transaction_info->method_id);
        }
        return null;
    }
    /**
     * Get current URL path
     */
    protected function getCurrentUrlPath()
    {
        return explode("?", \XLite\Core\URLManager::getCurrentURL())[0];
    }
    /**
     * Get current order number
     * @return string
     */
    protected function getOrderDetailUrl()
    {
        return $this->getCurrentUrlPath() . "?target=order&order_number=" . $this->getOrderNumber();        
    }
    /**
     * Get current order number
     */
    protected function getOrderNumber()
    {
        $request = \XLite\Core\Request::getInstance();
        return $request->order_number;
    }
    /**
     * Check if Payment is success or not
     */
    protected function isOrderSuccess()
    {
        return $this->getPaymentMethod() != null && $this->getTransaction() != null;
    }
    /**
     * Check to show refund history
     * @return boolean
     */
    protected function isShowRefundTitle()
    {
        return $this->canRefund() || !empty($this->getRefundHistory());
    }
    /**
     * Format datetime
     * {@inheritDoc}
     * @see \XLite\View\AView::formatDate()
     */
    protected function formatDateTime($val)
    {
        return $this->formatDate(strtotime($val)) . ", " . $this->formatDayTime(strtotime($val));//substr($val, 0, 16);
    }
}
