<?php
namespace XLite\Module\Digiwallet\Payment\client\src;

use XLite\Module\Digiwallet\Payment\client\src\Request\CheckTransactionInterface as CheckTransactionRequest;
use XLite\Module\Digiwallet\Payment\client\src\Request\CreateTransactionInterface as CreateTransactionRequest;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Class Client
 * @package XLite\Module\Digiwallet\Payment\client\src
 */
class Client extends GuzzleClient implements ClientInterface
{
    /**
     * Client constructor.
     */
    public function __construct($transactionClientDomain)
    {
        parent::__construct(['base_uri' => $transactionClientDomain]);
    }

    /**
     * @param CreateTransactionRequest $createTransaction
     * @return Response
     * @throws GuzzleException
     */
    public function createTransaction(CreateTransactionRequest $createTransaction): Response
    {
        return $this->send($createTransaction);
    }

    /**
     * @param CheckTransactionRequest $checkTransaction
     * @return Response
     * @throws GuzzleException
     */
    public function checkTransaction(CheckTransactionRequest $checkTransaction): Response
    {
        return $this->send($checkTransaction);
    }
}
