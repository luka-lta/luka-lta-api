<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\ApiKey\Create\Service;

use LukaLtaApi\Repository\ApiKeyRepository;
use LukaLtaApi\Value\ApiKey\ApiKeyObject;

class CreateApiKeyService
{
    public function __construct(
        private readonly ApiKeyRepository $repository,
    ) {
    }

    public function create(
        string $keyOrigin,
        int $createdBy,
        ?string $expiresAt,
        array $permissions,
    ): ApiKeyObject {
        $apiKey = ApiKeyObject::create(
            $keyOrigin,
            $createdBy,
            date('Y-m-d H:i:s'),
            $expiresAt,
            $permissions,
        );

        $this->repository->create($apiKey);

        return $apiKey;
    }
}
