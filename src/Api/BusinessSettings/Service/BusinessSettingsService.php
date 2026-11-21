<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\BusinessSettings\Service;

use Fig\Http\Message\StatusCodeInterface;
use finfo;
use LukaLtaApi\Exception\BusinessSettingsIncompleteException;
use LukaLtaApi\Exception\ProjectAssetUploadException;
use LukaLtaApi\Repository\BusinessSettingsRepository;
use LukaLtaApi\Repository\S3Repository;
use LukaLtaApi\Value\Business\BusinessSettings;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

class BusinessSettingsService
{
    /** 2 MiB in bytes */
    private const int MAX_LOGO_SIZE = 2 * 1024 * 1024;

    private const string LOGO_KEY_PREFIX = 'business/logo.';

    /** Erlaubte Logo-Formate (per Inhalt erkannt, nicht per Client-Angabe) */
    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function __construct(
        private readonly BusinessSettingsRepository $repository,
        private readonly S3Repository $s3Repository,
    ) {
    }

    public function getSettings(): ApiResult
    {
        $settings = $this->repository->loadSettings();

        return ApiResult::from(JsonResult::from('Business settings fetched', [
            'settings' => $settings?->toArray(),
        ]));
    }

    /** @param array<string, mixed> $data */
    public function updateSettings(array $data): ApiResult
    {
        $logoObjectKey = $this->repository->loadSettings()?->getLogoObjectKey();
        $this->repository->save(BusinessSettings::fromRequest($data, $logoObjectKey));

        return $this->getSettings();
    }

    public function uploadLogo(UploadedFileInterface $uploadedFile): ApiResult
    {
        $existing = $this->repository->loadSettings();
        if ($existing === null) {
            throw new BusinessSettingsIncompleteException();
        }

        $body = $uploadedFile->getStream()->getContents();
        $extension = $this->validateLogo($uploadedFile, $body);
        $objectKey = self::LOGO_KEY_PREFIX . $extension;
        $oldObjectKey = $existing->getLogoObjectKey();

        $this->s3Repository->putPrivateObject($objectKey, $body, array_flip(self::ALLOWED_MIME_TYPES)[$extension]);

        try {
            $this->repository->save($existing->withLogoObjectKey($objectKey));
        } catch (Throwable $exception) {
            // Das Objekt liegt schon im Bucket, aber nichts verweist darauf
            if ($oldObjectKey !== $objectKey) {
                $this->s3Repository->deleteObject($objectKey);
            }

            throw $exception;
        }

        // Altes Logo mit anderer Endung entfernen; gleicher Key wurde überschrieben
        if ($oldObjectKey !== null && $oldObjectKey !== $objectKey) {
            $this->s3Repository->deleteObject($oldObjectKey);
        }

        return $this->getSettings();
    }

    public function loadCompleteOrFail(): BusinessSettings
    {
        $settings = $this->repository->loadSettings();
        if ($settings === null || !$settings->isComplete()) {
            throw new BusinessSettingsIncompleteException();
        }

        return $settings;
    }

    private function validateLogo(UploadedFileInterface $uploadedFile, string $body): string
    {
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            throw new ProjectAssetUploadException(
                'File upload failed with error code ' . $uploadedFile->getError(),
                StatusCodeInterface::STATUS_BAD_REQUEST,
            );
        }

        $size = strlen($body);
        if ($size === 0) {
            throw new ProjectAssetUploadException('Uploaded file is empty', StatusCodeInterface::STATUS_BAD_REQUEST);
        }
        if ($size > self::MAX_LOGO_SIZE) {
            throw new ProjectAssetUploadException(
                'File size exceeds the maximum limit of 2MB',
                StatusCodeInterface::STATUS_BAD_REQUEST,
            );
        }

        $mimeType = (new finfo(FILEINFO_MIME_TYPE))->buffer($body);
        if ($mimeType === false || !array_key_exists($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new ProjectAssetUploadException(
                'Invalid file type. Only JPG and PNG are allowed',
                StatusCodeInterface::STATUS_BAD_REQUEST,
            );
        }

        return self::ALLOWED_MIME_TYPES[$mimeType];
    }
}
