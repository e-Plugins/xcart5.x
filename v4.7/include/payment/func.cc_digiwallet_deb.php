<?php

require_once $xcart_dir . '/payment/digiwallet.processor.class.php';

/**
 * Check refunding feature available or not by checking API token
 */
function func_cc_digiwallet_deb_get_refund_mode($paymentid, $orderid)
{
	if((new digiwallet_processor("deb", "DEB", "Digiwallet - Sofort"))->isRefundAvailable($paymentid, $orderid)) {
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
function func_cc_digiwallet_deb_do_refund($order, $total)
{
	return (new digiwallet_processor("deb", "DEB", "Digiwallet - Sofort"))->refund_order($order, $total);
}


