<?php

declare(strict_types=1);

namespace LukaLtaApi\Service\Contracts;

interface PreviewTokenValidationServiceInterface
{
    public function validatePreviewToken(string $token): void;
}
