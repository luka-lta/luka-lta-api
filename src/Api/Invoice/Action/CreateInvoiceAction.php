<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Invoice\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Invoice\Service\InvoiceService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X POST http://localhost/api/v1/invoices --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
//        --header 'Content-Type: application/json'
//        --data '{"customerId":"<uuid>","invoiceDate":"2026-11-20","serviceDate":"2026-11-15","paymentTermDays":14,
//        "items":[{"description":"Umsetzung","quantity":"4","unit":"Std.","unitPriceCents":9000}]}'
class CreateInvoiceAction extends ApiAction
{
    public function __construct(
        private readonly InvoiceService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->createInvoice((array) $request->getParsedBody())->getResponse($response);
    }
}
