<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * @file Provides support for Digiwallet iDEAL, Bancontact, Sofort, Creditcard and Paysafe
 *
 * @author Yellow Melon B.V.
 *         @url http://www.idealplugins.nl
 */
namespace XLite\Module\Digiwallet\Payment;

/**
 * Main module
 */
abstract class Main extends \XLite\Module\AModule
{

    const PP_METHOD_IDEAL = 'Digiwallet - iDEAL';

    const PP_METHOD_BANCONTACT = 'Digiwallet - Bancontact';

    const PP_METHOD_CREDITCARD = 'Digiwallet - Visa/Mastercard';

    const PP_METHOD_PAYSAFE = 'Digiwallet - PaysafeCard';

    const PP_METHOD_SOFORT = 'Digiwallet - Sofort';
    
    const PP_METHOD_PAYPAL = 'Digiwallet - PayPal';
    
    const PP_METHOD_BANKWIRE = 'Digiwallet - Bankwire - Overschrijvingen';


    /**
     * Returns payment method
     *
     * @param string $serviceName
     *            Service name
     * @param boolean $enabled
     *            Enabled status OPTIONAL
     *
     * @return \XLite\Model\Payment\Method
     */
    public static function getPaymentMethod($serviceName, $enabled = null)
    {
        if (! isset(static::$paymentMethod[$serviceName])) {
            static::$paymentMethod[$serviceName] = \XLite\Core\Database::getRepo('XLite\Model\Payment\Method')->findOneBy(array(
                'service_name' => $serviceName
            ));
            if (! static::$paymentMethod[$serviceName]) {
                static::$paymentMethod[$serviceName] = false;
            }
        }
        return static::$paymentMethod[$serviceName] && (is_null($enabled) || static::$paymentMethod[$serviceName]->getEnabled() === (bool) $enabled) ? static::$paymentMethod[$serviceName] : null;
    }
}
