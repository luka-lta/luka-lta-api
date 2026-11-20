<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Offer\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Offer\Service\OfferService;
use LukaLtaApi\Value\Business\OfferId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X POST http://localhost/api/v1/offers/<uuid>/send --header 'Authorization: <raw-jwt>'
//        --header 'Origin: <origin>'
class SendOfferAction extends ApiAction
{
    public function __construct(
        private readonly OfferService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $offerId = OfferId::fromString((string) $request->getAttribute('offerId'));

        return $this->service->send($offerId)->getResponse($response);
    }
}
