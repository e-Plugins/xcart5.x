<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * Copyright (c) 2011-present Qualiteam software Ltd.
 * All rights reserved.
 * See https://www.x-cart.com/license-agreement.html for license details.
 */
namespace XLite\Module\Digiwallet\Payment\Model;

/**
 * Digiwallet Refund model
 *
 * @Entity
 * @Table (name="digiwallet_refund",
 * indexes={
 * @Index (name="idx_digiwallet_refund", columns={"order_id", "transaction_id"})
 * }
 * )
 */
class TargetPayRefund extends \XLite\Model\AEntity
{

    /**
     * @Id
     * @GeneratedValue (strategy="AUTO")
     * @Column (type="integer", options={ "unsigned": true })
     */
    protected $id;
    /**
     * @Column (type="string", length=255, nullable=true)
     */
    protected $refund_id;
    /**
     * @Column (type="string", length=64)
     */
    protected $order_id;

    /**
     * @Column (type="string", length=255, nullable=true)
     */
    protected $transaction_id;

    /**
     * @Column (type="float", nullable=true)
     */
    protected $refund_amount;

    /**
     * @Column (type="string", length=1024, nullable=true)
     */
    protected $refund_message;

    /**
     * @Column (type="string", length=25, nullable=true)
     */
    protected $status;

    /**
     * @Column (type="datetime", nullable=true)
     */
    protected $datetimestamp;
    
    /**
     * @Column (type="datetime", nullable=true)
     */
    protected $last_modified;

    /**
     * Search the data by id
     *
     * @param unknown $digi_txid
     * @return \Doctrine\ORM\PersistentCollection|number
     */
    public function findById($id)
    {
        $repo = \XLite\Core\Database::getRepo('\XLite\Module\Digiwallet\Payment\Model\TargetPayRefund');
        $query = $repo->createQueryBuilder('digiwallet_refund')->where('digiwallet_refund.id = :id')->setParameter('id', $id)->getQuery();
        $result = $query->getResult();
        if(!empty($result)){
            return $result[0];
        }
        return null;
    }
    /**
     * Search the data by refund id
     *
     * @param unknown $digi_txid
     * @return \Doctrine\ORM\PersistentCollection|number
     */
    public function findByRefundId($refundid)
    {
        $repo = \XLite\Core\Database::getRepo('\XLite\Module\Digiwallet\Payment\Model\TargetPayRefund');
        $query = $repo->createQueryBuilder('digiwallet_refund')->where('digiwallet_refund.refund_id = :refundid')->setParameter('refundid', $refundid)->getQuery();
        $result = $query->getResult();
        if(!empty($result)){
            return $result[0];
        }
        return null;
    }
    /**
     * Search the data by refund id
     *
     * @param unknown $digi_txid
     * @return \Doctrine\ORM\PersistentCollection|number
     */
    public function findByOrderId($order_id)
    {
        $repo = \XLite\Core\Database::getRepo('\XLite\Module\Digiwallet\Payment\Model\TargetPayRefund');
        $query = $repo->createQueryBuilder('digiwallet_refund')->where('digiwallet_refund.order_id = :order_id')->setParameter('order_id', $order_id)->getQuery();
        $result = $query->getResult();
        return $result;
    }
}
