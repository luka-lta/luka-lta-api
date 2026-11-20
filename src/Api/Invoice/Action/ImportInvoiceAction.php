<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Invoice\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Invoice\Service\InvoiceImportService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X POST http://localhost/api/v1/invoices/import
//        --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
//        --form 'file=@<rechnung.pdf>' --form 'customerId=<uuid>' --form 'invoiceNumber=RE-2026-0001'
//        --form 'invoiceDate=2026-11-02' --form 'dueDate=2026-11-16' --form 'totalCents=120000'
//        --form 'status=paid' --form 'paidAt=2026-11-10' --form 'serviceDate=2026-10-30'
//        [--form 'allowNumberJump=1']  (1|true: Nummer darf über der nächsten freien liegen, sonst 409)
class ImportInvoiceAction extends ApiAction
{
    public function __construct(
        private readonly InvoiceImportService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service
            ->importInvoice((array) $request->getParsedBody(), $request->getUploadedFiles()['file'] ?? null)
            ->getResponse($response);
    }
}
