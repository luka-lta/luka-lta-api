<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Business;

/** Fertiges PDF samt Dateiname für den Download */
class PdfFile
{
    private function __construct(
        private readonly string $fileName,
        private readonly string $body,
    ) {
    }

    public static function from(string $fileName, string $body): self
    {
        return new self($fileName, $body);
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
