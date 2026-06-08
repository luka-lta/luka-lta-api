<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository\Contracts;

use LukaLtaApi\Value\User\UserId;
use Psr\Http\Message\UploadedFileInterface;

interface S3RepositoryInterface
{
    public function uploadFile(UploadedFileInterface $uploadedFile, UserId $userId): string;

    public function getAvatarImageFromS3(UserId $userId): ?array;
}
