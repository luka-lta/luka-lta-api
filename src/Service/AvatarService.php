<?php

declare(strict_types=1);

namespace LukaLtaApi\Service;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiAvatarUploadException;
use LukaLtaApi\Value\User\UserId;
use RuntimeException;

class AvatarService
{
    public function uploadAvatar(array $uploadedFiles, UserId $userId): string
    {
        $uploadDir = '/app/uploads/profile-pictures/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $uploadDir));
        }

        $uploadedFile = $uploadedFiles['avatar'];

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

        // Sicheren Dateinamen generieren (z. B. UUID statt Originalname)
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
        $filename = sprintf('%s/%s.%s', $uploadDir, $userId->asString(), $extension);

        $uploadedFile->moveTo($filename);
        return $filename;
    }
}
