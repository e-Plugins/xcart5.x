<?php
namespace XLite\Module\Digiwallet\Payment\client\src\Request;

use XLite\Module\Digiwallet\Payment\client\src\ClientInterface as TransactionClient;
use XLite\Module\Digiwallet\Payment\client\src\Response\CheckTransactionInterface as CheckTransactionResponseInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Interface TransactionRequest
 * @package XLite\Module\Digiwallet\Payment\client\src\Request
 */
interface CheckTransactionInterface extends RequestInterface
{
    /**
     * @return CheckTransactionInterface
     */
    public function enableTestMode(): self;

    /**
     * @param int $outletId
     * @return CheckTransactionInterface
     */
    public function withOutlet(int $outletId): self;

    /**
     * @param string $bearer
     * @return CheckTransactionInterface
     */
    public function withBearer(string $bearer): self;

    /**
     * @param int $transactionId
     * @return CheckTransactionInterface
     */
    public function withTransactionId(int $transactionId): self;

    /**
     * @return CheckTransactionResponseInterface
     */
    public function send(): CheckTransactionResponseInterface;
}
