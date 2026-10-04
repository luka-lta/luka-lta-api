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
use Throwable;

class ProjectAssetService
{
    /** 5 MiB in bytes */
    private const int MAX_FILE_SIZE = 5 * 1024 * 1024;

    /** Entspricht der Spaltenbreite von project_assets.alt_text */
    private const int MAX_ALT_TEXT_LENGTH = 150;

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
        if ($altText !== null && mb_strlen($altText) > self::MAX_ALT_TEXT_LENGTH) {
            throw new ProjectAssetUploadException(
                'Alt text must not exceed 150 characters.',
                StatusCodeInterface::STATUS_BAD_REQUEST,
            );
        }

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

        try {
            return $this->repository->create($asset);
        } catch (Throwable $exception) {
            // Das Objekt liegt bereits in MinIO, die Zeile fehlt: ohne diesen
            // Rollback bliebe ein Objekt zurueck, auf das nichts mehr verweist.
            $this->s3Repository->deleteObject($objectKey);

            throw $exception;
        }
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

    /**
     * Wie loadAssetOrFail(), prueft zusaetzlich, dass das Asset zum uebergebenen
     * Projekt gehoert. Bei Mismatch 404 statt 403, damit ein falsches Paar nicht
     * von einem nicht existierenden Asset unterscheidbar ist.
     */
    public function loadForProjectOrFail(ProjectId $projectId, string $assetId): ProjectAsset
    {
        $asset = $this->loadAssetOrFail($assetId);

        if ($asset->getProjectId()->asString() !== $projectId->asString()) {
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
        // Zeile zuerst loeschen: schlaegt das fehl, bleibt nur ein verwaistes
        // MinIO-Objekt zurueck (harmlos). In umgekehrter Reihenfolge wuerde ein
        // fehlgeschlagener Zeilen-Delete eine Zeile hinterlassen, die weiterhin
        // eine URL auf ein bereits geloeschtes Objekt in toArray() ausgibt.
        $this->repository->delete($asset->getAssetId());
        $this->s3Repository->deleteObject($asset->getObjectKey());
    }

    private function validate(UploadedFileInterface $uploadedFile): string
    {
        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            // Die meisten UPLOAD_ERR_* sind Client-Fehler (zu gross, abgebrochen,
            // nichts geschickt) und duerfen nicht als 500 erscheinen.
            $statusCode = match ($uploadedFile->getError()) {
                UPLOAD_ERR_INI_SIZE,
                UPLOAD_ERR_FORM_SIZE,
                UPLOAD_ERR_PARTIAL,
                UPLOAD_ERR_NO_FILE => StatusCodeInterface::STATUS_BAD_REQUEST,
                default => StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
            };

            throw new ProjectAssetUploadException(
                'File upload failed with error code ' . $uploadedFile->getError(),
                $statusCode,
            );
        }

        $mimeType = (string) $uploadedFile->getClientMediaType();

        if (!array_key_exists($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new ProjectAssetUploadException(
                'Invalid file type. Only JPG, PNG and WebP are allowed.',
                StatusCodeInterface::STATUS_BAD_REQUEST,
            );
        }

        if ($uploadedFile->getSize() === null || $uploadedFile->getSize() <= 0) {
            throw new ProjectAssetUploadException(
                'Uploaded file is empty.',
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
