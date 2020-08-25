<?php
namespace XLite\Module\Digiwallet\Payment\client\src\InvoiceLine;

use XLite\Module\Digiwallet\Payment\client\src\InvoiceLine\InvoiceLineInterface as InvoiceLine;
/**
 * Interface FactoryInterface
 * @package XLite\Module\Digiwallet\Payment\client\src\InvoiceLine
 */
interface FactoryInterface
{
    /**
     * @param string $productCode
     * @param string $productDescription
     * @param int $quantity
     * @param int $price
     * @param string $taxCategory
     * @return InvoiceLineInterface
     */
    public function create(
        string $productCode,
        string $productDescription,
        int $quantity,
        int $price,
        string $taxCategory
    ): InvoiceLine;
}
