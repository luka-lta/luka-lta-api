<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Offer\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Offer\Service\OfferService;
use LukaLtaApi\Value\Business\OfferId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl http://localhost/api/v1/offers/<uuid>/pdf --header 'Authorization: <raw-jwt>'
//        --header 'Origin: <origin>' --output angebot.pdf
class GetOfferPdfAction extends ApiAction
{
    public function __construct(
        private readonly OfferService $service,
    ) {
    }

    /** Versendet: gespeichertes PDF; Entwurf: Vorschau mit Wasserzeichen */
    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $offerId = OfferId::fromString((string) $request->getAttribute('offerId'));
        $pdf = $this->service->loadPdf($offerId);

        $response->getBody()->write($pdf->getBody());

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', sprintf('attachment; filename="%s"', $pdf->getFileName()))
            ->withHeader('Cache-Control', 'private, no-store');
    }
}
