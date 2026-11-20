<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Offer\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Offer\Service\OfferService;
use LukaLtaApi\Exception\ApiValidationException;
use LukaLtaApi\Value\Business\CustomerId;
use LukaLtaApi\Value\Business\OfferStatus;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl 'http://localhost/api/v1/offers?status=sent&customerId=<uuid>&year=2026'
//        --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
class ListOffersAction extends ApiAction
{
    public function __construct(
        private readonly OfferService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();

        return $this->service
            ->listOffers(
                $this->parseStatus($query['status'] ?? null),
                $this->parseCustomerId($query['customerId'] ?? null),
                $this->parseYear($query['year'] ?? null),
            )
            ->getResponse($response);
    }

    private function parseStatus(mixed $value): ?OfferStatus
    {
        if ($value === null || $value === '') {
            return null;
        }

        $status = is_string($value) ? OfferStatus::tryFrom($value) : null;
        if ($status === null) {
            throw new ApiValidationException('status must be one of draft, sent, accepted, rejected', 400);
        }

        return $status;
    }

    private function parseCustomerId(mixed $value): ?CustomerId
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CustomerId::fromString(is_string($value) ? $value : '');
    }

    private function parseYear(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || preg_match('/^\d{4}$/', $value) !== 1) {
            throw new ApiValidationException('year must have 4 digits', 400);
        }

        return (int) $value;
    }
}
