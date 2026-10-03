<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectAssetService;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X DELETE http://localhost/api/v1/projects/<uuid>/assets/<assetId>
//        --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
class DeleteProjectAssetAction extends ApiAction
{
    public function __construct(
        private readonly ProjectAssetService $assetService,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $assetId = (string) $request->getAttribute('assetId');
        $this->assetService->delete($assetId);

        return ApiResult::from(JsonResult::from('Project asset deleted.'), 204)->getResponse($response);
    }
}
