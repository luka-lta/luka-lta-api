<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Offer\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Offer\Service\OfferService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X POST http://localhost/api/v1/offers --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
//        --header 'Content-Type: application/json'
//        --data '{"customerId":"<uuid>","offerDate":"2026-11-20","introText":"Vielen Dank für Ihre Anfrage.",
//        "items":[{"description":"Konzeption","quantity":"1.5","unit":"Std.","unitPriceCents":9000}]}'
class CreateOfferAction extends ApiAction
{
    public function __construct(
        private readonly OfferService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->createOffer((array) $request->getParsedBody())->getResponse($response);
    }
}
