<?php

namespace XLite\Module\Digiwallet\Payment\client\src\Response;

/**
 * Interface TransactionResponse
 * @package XLite\Module\Digiwallet\Payment\client\src\Response
 */
interface CreateTransactionInterface
{
    /**
     * @return int
     */
    public function status(): int;

    /**
     * @return string
     */
    public function message(): string;

    /**
     * @return int
     */
    public function transactionId(): int;

    /**
     * @return string
     */
    public function launchUrl(): string;

    /**
     * @return string
     */
    public function response(): array;

    /**
     * @return string
     */
    public function transactionKey(): string;

}
