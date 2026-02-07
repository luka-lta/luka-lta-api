<?php

declare(strict_types=1);

namespace LukaLtaApi\Value;

use UAParser\Parser;

class UserAgent
{
    public function __construct(
        private readonly string $userAgentString,
        private readonly string $browserName,
        private readonly string $browserVersion,
        private readonly string $osName,
        private readonly string $osVersion,
    ) {
    }

    public static function fromUserAgent(string $userAgentString): self
    {
        $parser = Parser::create();
        $result = $parser->parse($userAgentString);

        return new self(
            $userAgentString,
            $result->ua->family,
            $result->ua->major ?? 'Unknown',
            $result->os->family,
            $result->os->major ?? 'Unknown',
        );
    }

    public function getUserAgentString(): string
    {
        return $this->userAgentString;
    }

    public function getBrowserName(): string
    {
        return $this->browserName;
    }

    public function getBrowserVersion(): string
    {
        return $this->browserVersion;
    }

    public function getOsName(): string
    {
        return $this->osName;
    }

    public function getOsVersion(): string
    {
        return $this->osVersion;
    }
}
