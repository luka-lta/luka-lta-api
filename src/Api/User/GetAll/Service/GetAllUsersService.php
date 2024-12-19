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
        $links = $this->repository->getAll();

        if ($links === null) {
            return null;
        }

        // Wandelt jedes User-Objekt in ein Array um
        return array_map(static fn(User $link) => $link->toArray(), $links);
    }
}
