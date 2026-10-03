<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ProjectAssetNotFoundException;
use LukaLtaApi\Exception\ProjectAssetUploadException;
use LukaLtaApi\Repository\ProjectAssetRepository;
use LukaLtaApi\Repository\S3Repository;
use LukaLtaApi\Value\Project\Asset\ProjectAsset;
use LukaLtaApi\Value\Project\Asset\ProjectAssetType;
use LukaLtaApi\Value\Project\ProjectId;
use Psr\Http\Message\UploadedFileInterface;
use Ramsey\Uuid\Uuid;

class ProjectAssetService
{
    /** 5 MiB in bytes */
    private const int MAX_FILE_SIZE = 5 * 1024 * 1024;

    /** Erlaubte Bild-Formate. Bewusst inkl. webp, anders als der Avatar-Upload. */
    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly ProjectAssetRepository $repository,
        private readonly S3Repository           $s3Repository,
    ) {
    }

    public function upload(
        ProjectId             $projectId,
        ProjectAssetType      $type,
        UploadedFileInterface $uploadedFile,
        ?string               $altText,
    ): ProjectAsset {
        $extension = $this->validate($uploadedFile);

        // logo/cover existieren pro Projekt nur einmal: altes Asset inkl.
        // MinIO-Objekt vor dem Anlegen des neuen entfernen.
        if ($type->isSingleton()) {
            $existing = $this->repository->getByProject($projectId)->ofType($type);

            if ($existing !== null) {
                $this->deleteAsset($existing);
            }
        }

        $sortOrder = $type === ProjectAssetType::SCREENSHOT
            ? $this->repository->getNextSortOrder($projectId)
            : 0;

        $objectKey = sprintf(
            'projects/%s/%s/%s.%s',
            $projectId->asString(),
            $type->value,
            Uuid::uuid4()->toString(),
            $extension,
        );

        $this->s3Repository->uploadProjectAsset($uploadedFile, $objectKey);

        $asset = ProjectAsset::create($projectId, $type, $objectKey, $altText, $sortOrder);

        return $this->repository->create($asset);
    }

    public function delete(string $assetId): void
    {
        $this->deleteAsset($this->loadAssetOrFail($assetId));
    }

    /**
     * Die DB-CASCADE auf project_assets raeumt nur Zeilen auf — die
     * MinIO-Objekte muss die Anwendung selbst entfernen, deshalb vor dem
     * Loeschen des Projekts aufrufen.
     */
    public function deleteAllForProject(ProjectId $projectId): void
    {
        foreach ($this->repository->getByProject($projectId) as $asset) {
            $this->deleteAsset($asset);
        }
    }

    public function loadAssetOrFail(string $assetId): ProjectAsset
    {
        $asset = $this->repository->getById($assetId);

        if ($asset === null) {
            throw new ProjectAssetNotFoundException();
        }

        return $asset;
    }

    public function getObjectForAsset(ProjectAsset $asset): ?array
    {
        return $this->s3Repository->getObject($asset->getObjectKey());
    }

    private function deleteAsset(ProjectAsset $asset): void
    {
        $this->s3Repository->deleteObject($asset->getObjectKey());
        $this->repository->delete($asset->getAssetId());
    }

    private function validate(UploadedFileInterface $uploadedFile): string
    {
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            throw new ProjectAssetUploadException(
                'File upload failed with error code ' . $uploadedFile->getError(),
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
            );
        }

        $mimeType = (string) $uploadedFile->getClientMediaType();

        if (!array_key_exists($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new ProjectAssetUploadException(
                'Invalid file type. Only JPG, PNG and WebP are allowed.',
                StatusCodeInterface::STATUS_BAD_REQUEST,
            );
        }

        if ($uploadedFile->getSize() > self::MAX_FILE_SIZE) {
            throw new ProjectAssetUploadException(
                'File size exceeds the maximum limit of 5MB.',
                StatusCodeInterface::STATUS_BAD_REQUEST,
            );
        }

        return self::ALLOWED_MIME_TYPES[$mimeType];
    }
}
