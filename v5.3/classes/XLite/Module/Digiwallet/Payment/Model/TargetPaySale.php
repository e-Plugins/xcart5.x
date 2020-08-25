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
 * @Table (name="digiwallet_transaction",
 * indexes={
 * @Index (name="idx_transaction_infos", columns={"order_id", "method"})
 * }
 * )
 */
class TargetPaySale extends \XLite\Model\AEntity
{

    /**
     * @Id
     * @GeneratedValue (strategy="AUTO")
     * @Column (type="integer", options={ "unsigned": true })
     */
    protected $id;

    /**
     * @Column (type="string", length=64)
     */
    protected $order_id;

    /**
     * @Column (type="string", length=10, nullable=true)
     */
    protected $method;

    /**
     * @Column (type="string", length=25, nullable=true)
     */
    protected $method_id;

    /**
     * @Column (type="integer", nullable=true)
     */
    protected $amount;

    /**
     * @Column (type="string", length=64, nullable=true)
     */
    protected $digi_txid;

    /**
     * @Column (type="string", length=1024, nullable=true)
     */
    protected $digi_response;

    /**
     * @Column (type="string", length=10, nullable=true)
     */
    protected $status;
    
    /**
     * @Column (type="datetime", nullable=true)
     */
    protected $paid;
    
    /**
     * @Column (type="text", nullable=true)
     */
    protected $more;

    /**
     * Search the data by Digiwallet id
     *
     * @param unknown $digi_txid
     * @return \Doctrine\ORM\PersistentCollection|number
     */
    public function findByTargetPayId($digi_txid)
    {
        $repo = \XLite\Core\Database::getRepo('\XLite\Module\Digiwallet\Payment\Model\TargetPaySale');
        $query = $repo->createQueryBuilder('target_sale')->where('target_sale.digi_txid = :digi_txid')->setParameter('digi_txid', $digi_txid)->getQuery();
        $result = $query->getResult();
        if(!empty($result)){
            return $result[0];
        }
        return null;
    }
    /**
     * Search the data by id
     *
     * @param unknown $id
     * @return \Doctrine\ORM\PersistentCollection|number
     */
    public function findById($id)
    {
        $repo = \XLite\Core\Database::getRepo('\XLite\Module\Digiwallet\Payment\Model\TargetPaySale');
        $query = $repo->createQueryBuilder('target_sale')->where('target_sale.id = :id')->setParameter('id', $id)->getQuery();
        $result = $query->getResult();
        if(!empty($result)){
            return $result[0];
        }
        return null;
    }
    /**
     * Search by OrderID
     *
     * @param unknown $orderid
     * @return \Doctrine\ORM\PersistentCollection|number
     */
    public function findByOrderId($orderid)
    {
        $repo = \XLite\Core\Database::getRepo('\XLite\Module\Digiwallet\Payment\Model\TargetPaySale');
        $query = $repo->createQueryBuilder('target_sale')->where('target_sale.order_id = :orderid')->setParameter('orderid', $orderid)->getQuery();
        $result = $query->getResult();
        if(!empty($result)){
            return $result[0];
        }
        return null;
    }
    /**
     * Check payment success
     *
     * @param unknown $orderid
     */
    public function getSuccessTransaction($orderid)
    {
        $repo = \XLite\Core\Database::getRepo('\XLite\Module\Digiwallet\Payment\Model\TargetPaySale');
        $query = $repo->createQueryBuilder('target_sale')->where("target_sale.paid IS NOT NULL")->andWhere('target_sale.order_id = :orderid')->setParameter('orderid', $orderid)->getQuery();
        $result = $query->getResult();
        if(!empty($result)){
            return $result[0];
        }
        return false;
    }
}
