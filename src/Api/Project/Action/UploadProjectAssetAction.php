<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectAssetService;
use LukaLtaApi\Api\Project\Service\ProjectService;
use LukaLtaApi\Exception\ProjectAssetUploadException;
use LukaLtaApi\Value\Project\Asset\ProjectAssetType;
use LukaLtaApi\Value\Project\ProjectId;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X POST http://localhost/api/v1/projects/<uuid>/assets
//        --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>' --form 'type=logo' --form 'file=@logo.png'
class UploadProjectAssetAction extends ApiAction
{
    public function __construct(
        private readonly ProjectService      $projectService,
        private readonly ProjectAssetService $assetService,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $projectId = ProjectId::fromString((string) $request->getAttribute('projectId'));
        $this->projectService->loadProjectOrFail($projectId);

        $body = (array) $request->getParsedBody();
        $type = ProjectAssetType::fromString((string) ($body['type'] ?? ''));

        $uploadedFile = $request->getUploadedFiles()['file'] ?? null;

        if ($uploadedFile === null) {
            throw new ProjectAssetUploadException('Form field file is required.', 400);
        }

        $altText = isset($body['altText']) && trim((string) $body['altText']) !== ''
            ? trim((string) $body['altText'])
            : null;

        $asset = $this->assetService->upload($projectId, $type, $uploadedFile, $altText);

        return ApiResult::from(
            JsonResult::from('Project asset uploaded.', [
                'asset' => $asset->toArray($this->projectService->getAssetBaseUrl()),
            ]),
            201,
        )->getResponse($response);
    }
}
