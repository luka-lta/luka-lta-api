<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\PreviewToken\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\PreviewTokenRepository;
use LukaLtaApi\Value\Preview\PreviewToken;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\User\UserId;
use Psr\Http\Message\ServerRequestInterface;

class PreviewTokenService
{
    public function __construct(
        private readonly PreviewTokenRepository $repository,
    ) {
    }

    public function createToken(ServerRequestInterface $request): ApiResult
    {
        $userId = UserId::fromString($request->getAttribute('userId'));

        $token = PreviewToken::create(
            PreviewToken::generateToken(),
            $userId,
        );

        $this->repository->createToken($token);

        return ApiResult::from(
            JsonResult::from('Token created', ['token' => $token->getToken()]),
            StatusCodeInterface::STATUS_CREATED,
        );
    }
}
