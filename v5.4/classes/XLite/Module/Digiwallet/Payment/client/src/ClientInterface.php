<?php
namespace XLite\Module\Digiwallet\Payment\client\src;

use XLite\Module\Digiwallet\Payment\client\src\Request\CheckTransactionInterface as CheckTransactionRequest;
use XLite\Module\Digiwallet\Payment\client\src\Request\CreateTransactionInterface as CreateTransactionRequest;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Interface ClientInterface
 * @package XLite\Module\Digiwallet\Payment\client\src
 */
interface ClientInterface extends \GuzzleHttp\ClientInterface
{
    /**
     * @param CreateTransactionRequest $request
     * @return Response
     * @throws GuzzleException
     */
    public function createTransaction(CreateTransactionRequest $request): Response;

    /**
     * @param CheckTransactionRequest $request
     * @return Response
     * @throws GuzzleException
     */
    public function checkTransaction(CheckTransactionRequest $request): Response;
}
