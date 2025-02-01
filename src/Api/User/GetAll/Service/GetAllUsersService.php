<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\User\GetAll\Service;

use LukaLtaApi\Repository\UserRepository;
use LukaLtaApi\Value\User\User;

class GetAllUsersService
{
    public function __construct(
        private readonly UserRepository $repository
    ) {
    }

    public function getAll(): ?array
    {
        $users = $this->repository->getAll();

        if ($users->count() === 0) {
            return null;
        }

        return $users->toArray();
    }
}
