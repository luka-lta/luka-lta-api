<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Offer\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Offer\Service\OfferService;
use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Value\Business\OfferId;
use LukaLtaApi\Value\Business\OfferStatus;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X POST http://localhost/api/v1/offers/<uuid>/status
//        --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
//        --header 'Content-Type: application/json' --data '{"status":"accepted"}'
class SetOfferStatusAction extends ApiAction
{
    public function __construct(
        private readonly OfferService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $offerId = OfferId::fromString((string) $request->getAttribute('offerId'));
        $value = ((array) $request->getParsedBody())['status'] ?? null;
        $status = is_string($value) ? OfferStatus::tryFrom($value) : null;
        if ($status === null || !$status->isDecision()) {
            throw new ApiValidationException('status must be accepted or rejected', 400);
        }

        return $this->service->setStatus($offerId, $status)->getResponse($response);
    }
}
