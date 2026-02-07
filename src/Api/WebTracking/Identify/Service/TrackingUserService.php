<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\WebTracking\Identify\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Repository\SiteRepository;
use LukaLtaApi\Repository\TrackingUserAliasRepository;
use LukaLtaApi\Service\CryptService;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\Tracking\User\TrackingUser;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class TrackingUserService
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly CryptService $cryptService,
        private readonly TrackingUserAliasRepository $trackingUserAliasRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function identifyUser(ServerRequestInterface $request): ApiResult
    {
        $body = $request->getParsedBody();

        $siteConfiguration = $this->siteRepository->getSite($body['siteId']);
        if (!$siteConfiguration) {
            return ApiResult::from(
                JsonResult::from('Site not found'),
                StatusCodeInterface::STATUS_NOT_FOUND
            );
        }

        $siteId = $siteConfiguration->getSiteId();

        $userId = $body['userId'];
        $ipAddress = $request->getServerParams()['REMOTE_ADDR'];
        $userAgent = $request->getServerParams()['HTTP_USER_AGENT'];
        $anonymousId = $this->cryptService->generateAnonymousId($ipAddress, $userAgent);

        $trackingUser = TrackingUser::from((int)$siteConfiguration->getSiteId(), $anonymousId, $userId);

        if ($body['isNewIdentified']) {
            $existingAlias = $this->trackingUserAliasRepository->getUserAlias((int)$siteId, $anonymousId);

            if (!$existingAlias) {
                $this->trackingUserAliasRepository->insertUserAlias($trackingUser);

                return ApiResult::from(
                    JsonResult::from(
                        'Success'
                    )
                );
            }
            // Anonymous ID already linked to a different user - log but don't error
            $this->logger->warning('Anonymous ID already linked to a different user', [
                'siteId' => $siteId,
                'anonymousId' => $anonymousId,
                'userAgent' => $userAgent,
            ]);
        }

        return ApiResult::from(
            JsonResult::from(
                'Success'
            )
        );
    }
}
