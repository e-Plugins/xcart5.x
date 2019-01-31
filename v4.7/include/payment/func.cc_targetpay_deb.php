<?php

require_once $xcart_dir . '/payment/targetpay.processor.class.php';

/**
 * Check refunding feature available or not by checking API token
 */
function func_cc_targetpay_deb_get_refund_mode($paymentid, $orderid)
{
	if((new targetpay_processor("deb", "DEB", "Digiwallet - Sofort"))->isRefundAvailable($paymentid, $orderid)) {
		return "P";
	}
	return "";
}
/**
 * Process refund via Digiwallet
 *
 * @param Order object $order
 * @param int $amount
 */
function func_cc_targetpay_deb_do_refund($order, $total)
{
	return (new targetpay_processor("deb", "DEB", "Digiwallet - Sofort"))->refund_order($order, $total);
}


