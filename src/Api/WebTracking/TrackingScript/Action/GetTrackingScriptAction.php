<?php

namespace LukaLtaApi\Api\WebTracking\TrackingScript\Action;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Api\ApiAction;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class GetTrackingScriptAction extends ApiAction
{
    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $file = __DIR__ . '/../../../public/script.js';

        if (!file_exists($file)) {
            $response->getBody()->write('File not found');
            return $response->withStatus(StatusCodeInterface::STATUS_NOT_FOUND);
        }

        $content = file_get_contents($file);

        $response->getBody()->write($content);

        return $response->withHeader('Content-Type', 'application/javascript')
            ->withStatus(StatusCodeInterface::STATUS_OK);
    }
}