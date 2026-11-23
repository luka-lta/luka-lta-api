<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Invoice\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Invoice\Service\InvoiceService;
use LukaLtaApi\Value\Business\InvoiceId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl http://localhost/api/v1/invoices/<uuid>/pdf --header 'Authorization: <raw-jwt>'
//        --header 'Origin: <origin>' --output rechnung.pdf
class GetInvoicePdfAction extends ApiAction
{
    public function __construct(
        private readonly InvoiceService $service,
    ) {
    }

    /** Festgeschrieben: gespeichertes PDF; Entwurf: Vorschau mit Wasserzeichen */
    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $invoiceId = InvoiceId::fromString((string) $request->getAttribute('invoiceId'));
        $pdf = $this->service->loadPdf($invoiceId);

        $response->getBody()->write($pdf->getBody());

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', sprintf('attachment; filename="%s"', $pdf->getFileName()))
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
