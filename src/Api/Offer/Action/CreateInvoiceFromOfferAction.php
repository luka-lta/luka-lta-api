<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Offer\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Invoice\Service\InvoiceService;
use LukaLtaApi\Value\Business\OfferId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X POST http://localhost/api/v1/offers/<uuid>/invoices --header 'Authorization: <raw-jwt>'
//        --header 'Origin: <origin>' --header 'Content-Type: application/json'
//        --data '{"kind":"deposit","amountCents":40000}'   (oder {"kind":"deposit","percent":33.333},
//        {"kind":"final"}, {"kind":"standard"})
class CreateInvoiceFromOfferAction extends ApiAction
{
    public function __construct(
        private readonly InvoiceService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $offerId = OfferId::fromString((string) $request->getAttribute('offerId'));

        return $this->service->createFromOffer($offerId, (array) $request->getParsedBody())->getResponse($response);
    }
}
