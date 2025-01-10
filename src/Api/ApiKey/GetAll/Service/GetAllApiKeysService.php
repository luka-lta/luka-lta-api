<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\ApiKey\GetAll\Service;

use LukaLtaApi\Repository\ApiKeyRepository;
use LukaLtaApi\Value\ApiKey\ApiKeyObject;

class GetAllApiKeysService
{
    public function __construct(
        private readonly ApiKeyRepository $repository,
    ) {
    }

    public function loadAll(): ?array
    {
        $apiKeys = $this->repository->loadAll();

        if ($apiKeys === null) {
            return null;
        }

        return array_map(static fn(ApiKeyObject $key) => $key->toArray(), $apiKeys);
    }
}
