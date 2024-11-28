<?php

namespace LukaLtaApi\Value;

interface IdentifierInterface
{
    public function asString(): string;
    public function asInt(): int;
}
