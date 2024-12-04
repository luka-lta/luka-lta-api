<?php

namespace LukaLtaApi\Api\Click\Track;

use DateTimeImmutable;
use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Click\Track\Service\ClickTrackService;
use LukaLtaApi\Exception\MissingTargetUrlException;
use LukaLtaApi\Value\LinkCollection\LinkUrl;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\Tracking\Click;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ClickTrackAction extends ApiAction
{
    public function __construct(
        private readonly ClickTrackService $service
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $targetUrl = $request->getQueryParams()['targetUrl'] ?? null;

        if ($targetUrl === null) {
            throw new MissingTargetUrlException(
                'Missing targetUrl parameter',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        $targetUrl = LinkUrl::fromString(urldecode($targetUrl));


        $ipAdress = $request->getServerParams()['REMOTE_ADDR'] ?? null;
        $userAgent = $request->getServerParams()['HTTP_USER_AGENT'] ?? null;
        $referer = $request->getServerParams()['HTTP_REFERER'] ?? null;

        $this->service->track(
            Click::from(
                null,
                $targetUrl,
                new DateTimeImmutable(),
                $ipAdress,
                $userAgent,
                $referer
            )
        );

        return ApiResult::from(
            JsonResult::from('Click tracked'),
            StatusCodeInterface::STATUS_FOUND
        )
            ->getResponse($response)
            ->withHeader('Location', (string)$targetUrl);
    }
}
