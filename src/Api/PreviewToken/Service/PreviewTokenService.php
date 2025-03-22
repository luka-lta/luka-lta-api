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
        $body = $request->getParsedBody();
        $createdBy = UserId::fromString($request->getAttribute('userId'));
        $maxUse = $body['maxUse'] ?? 1;
        $isActive = $body['isActive'] ?? true;

        $token = PreviewToken::create(
            PreviewToken::generateToken(),
            $maxUse,
            $isActive,
            $createdBy,
        );

        $this->repository->createToken($token);

        return ApiResult::from(
            JsonResult::from('Token created', ['token' => $token->getToken()]),
            StatusCodeInterface::STATUS_CREATED,
        );
    }
}
