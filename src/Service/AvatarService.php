<?php

declare(strict_types=1);

namespace LukaLtaApi\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiAvatarUploadException;
use LukaLtaApi\Repository\S3Repository;
use LukaLtaApi\Value\User\UserId;
use Slim\Psr7\UploadedFile;

class AvatarService
{
    public function __construct(
        private readonly S3Repository $s3Repository,
    ) {
    }

    public function uploadAvatar(UploadedFile $uploadedFiles, UserId $userId): string
    {
        $uploadedFile = $uploadedFiles;

        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            throw new ApiAvatarUploadException(
                'File upload error',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png'];
        $mimeType = $uploadedFile->getClientMediaType();

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new ApiAvatarUploadException(
                'Invalid file type. Only JPG and PNG are allowed.',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        if ($uploadedFile->getSize() > 5 * 1024 * 1024) {
            throw new ApiAvatarUploadException(
                'File size exceeds the maximum limit of 5MB.',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }

        return $this->s3Repository->uploadFile($uploadedFile, $userId);
    }
}
