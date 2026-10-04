<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectAssetService;
use LukaLtaApi\Exception\ProjectAssetNotFoundException;
use LukaLtaApi\Value\Project\ProjectId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl http://localhost/api/v1/projects/<uuid>/assets/<assetId> --output logo.png
class GetProjectAssetAction extends ApiAction
{
    public function __construct(
        private readonly ProjectAssetService $assetService,
    ) {
    }

    /**
     * Liefert die Bytes ueber die eigene API statt per MinIO-URL — Endpoint und
     * Credentials des Object Storage bleiben damit serverseitig. Route ist
     * oeffentlich, weil das Portfolio Bilder ohne Login laden muss.
     */
    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $projectId = ProjectId::fromString((string) $request->getAttribute('projectId'));
        $asset     = $this->assetService->loadForProjectOrFail($projectId, (string) $request->getAttribute('assetId'));

        $object = $this->assetService->getObjectForAsset($asset);

        if ($object === null) {
            throw new ProjectAssetNotFoundException('Project asset object not found in storage.');
        }

        $response->getBody()->write($object['body']);

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', $object['contentType'])
            ->withHeader('Cache-Control', 'public, max-age=86400');
    }
}
