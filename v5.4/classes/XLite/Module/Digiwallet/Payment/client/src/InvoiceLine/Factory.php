<?php
namespace XLite\Module\Digiwallet\Payment\client\src\InvoiceLine;

/**
 * Class Factory
 * @package XLite\Module\Digiwallet\Payment\client\src\InvoiceLine
 */
class Factory implements FactoryInterface
{
    public function create(
        string $productCode,
        string $productDescription,
        int $quantity,
        int $price,
        string $taxCategory
    ): InvoiceLineInterface {
        return new InvoiceLine($productCode, $productDescription, $quantity, $price, $taxCategory);
    }
}
