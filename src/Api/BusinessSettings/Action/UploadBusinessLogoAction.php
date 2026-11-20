<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\BusinessSettings\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\BusinessSettings\Service\BusinessSettingsService;
use LukaLtaApi\Exception\ProjectAssetUploadException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X POST http://localhost/api/v1/business/settings/logo
//        --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>' --form 'file=@logo.png'
class UploadBusinessLogoAction extends ApiAction
{
    public function __construct(
        private readonly BusinessSettingsService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $uploadedFile = $request->getUploadedFiles()['file'] ?? null;
        if ($uploadedFile === null) {
            throw new ProjectAssetUploadException('Form field file is required', 400);
        }

        return $this->service->uploadLogo($uploadedFile)->getResponse($response);
    }
}
