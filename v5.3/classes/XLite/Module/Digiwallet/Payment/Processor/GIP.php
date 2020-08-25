<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * @file Provides support for Digiwallet GIP, Mister Cash, Sofort Banking, Credit and Paysafe
 *
 * @author Yellow Melon B.V.
 *         @url http://www.iDealplugins.nl
 */
namespace XLite\Module\Digiwallet\Payment\Processor;

use XLite\Module\Digiwallet\Payment\Base\UnifiedPaymentCore;

class GIP extends UnifiedPaymentCore
{

    /**
     * The contructor
     */
    public function __construct()
    {
        $this->payMethod = "GIP";
        $this->currency = "EUR";
        $this->language = 'nl';
        $this->allow_nobank = true;
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
        return 'modules/Digiwallet/Payment/GIP.twig';
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
        return 'modules/Digiwallet/Payment/checkout/GIP.twig';
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
        $this->handlePaymentResult($transaction, false);
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
        $this->handlePaymentResult($transaction, true);
    }
}
