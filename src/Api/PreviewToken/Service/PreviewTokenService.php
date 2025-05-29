<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\PreviewToken\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\PreviewTokenRepository;
use LukaLtaApi\Repository\UserRepository;
use LukaLtaApi\Value\Preview\PreviewToken;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\User\UserId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class PreviewTokenService
{
    public function __construct(
        private readonly PreviewTokenRepository $repository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function createToken(ServerRequestInterface $request): ApiResult
    {
        $body = $request->getParsedBody();
        $userId = UserId::fromString($request->getAttribute('userId'));
        $maxUse = $body['maxUse'] ?? 1;
        $isActive = $body['isActive'] ?? true;

        $createdBy = $this->userRepository->findById($userId);

        if (!$createdBy) {
            return ApiResult::from(
                JsonResult::from('User not found'),
                StatusCodeInterface::STATUS_NOT_FOUND,
            );
        }

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

    public function listTokens(): ApiResult
    {
        $tokens = $this->repository->listTokens();

        if ($tokens->count() === 0) {
            return ApiResult::from(
                JsonResult::from('No tokens found', [
                    'tokens' => [],
                ]),
            );
        }

        return ApiResult::from(
            JsonResult::from('Tokens listed', ['tokens' => $tokens->toArray()])
        );
    }

    public function editToken(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $token = $this->repository->getToken($request->getAttribute('tokenId'));

        if (!$token) {
            return ApiResult::from(
                JsonResult::from('Token not found'),
            )->getResponse($response);
        }

        $body = $request->getParsedBody();

        $token->setIsActive($body['isActive']);
        $token->setMaxUse($body['maxUse']);

        $this->repository->updateToken($token);

        return ApiResult::from(
            JsonResult::from('Token edited', ['token' => $token->getToken()]),
        )->getResponse($response);
    }

    public function deleteToken(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $token = $this->repository->getToken($request->getAttribute('tokenId'));

        if (!$token) {
            return ApiResult::from(
                JsonResult::from('Token not found'),
            )->getResponse($response);
        }

        $this->repository->deleteToken($token->getToken());

        return ApiResult::from(
            JsonResult::from('Token deleted'),
        )->getResponse($response);
    }
}
