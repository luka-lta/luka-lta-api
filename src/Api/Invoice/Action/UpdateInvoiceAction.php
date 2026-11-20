<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Invoice\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Invoice\Service\InvoiceService;
use LukaLtaApi\Value\Business\InvoiceId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X PATCH http://localhost/api/v1/invoices/<uuid>
//        --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
//        --header 'Content-Type: application/json'
//        --data '{"servicePeriodStart":"2026-11-01","servicePeriodEnd":"2026-11-15"}'
class UpdateInvoiceAction extends ApiAction
{
    public function __construct(
        private readonly InvoiceService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $invoiceId = InvoiceId::fromString((string) $request->getAttribute('invoiceId'));

        return $this->service
            ->updateInvoice($invoiceId, (array) $request->getParsedBody())
            ->getResponse($response);
    }
}
