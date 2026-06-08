<?php

declare(strict_types=1);

namespace LukaLtaApi\Service\Contracts;

use LukaLtaApi\Value\User\UserId;
use Slim\Psr7\UploadedFile;

interface AvatarServiceInterface
{
    public function uploadAvatar(UploadedFile $uploadedFiles, UserId $userId): string;
}
