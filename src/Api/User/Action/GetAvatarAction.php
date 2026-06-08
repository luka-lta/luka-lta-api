<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\User\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\User\Service\UserAvatarService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetAvatarAction extends ApiAction
{
    public function __construct(
        private readonly UserAvatarService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getAvatar($request, $response);
    }
}
