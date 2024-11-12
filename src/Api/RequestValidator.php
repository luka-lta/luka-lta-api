<?php

namespace LukaLtaApi\Api;

use LukaLtaApi\Exception\ApiInvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

class RequestValidator
{
    public function validate(
        ServerRequestInterface $request,
        array $rules
    ): void {
        $queryParams = $request->getQueryParams();
        $bodyParams = $request->getParsedBody();

        foreach ($rules as $param => $rule) {
            $isRequired = $rule['required'] ?? false;
            $location = $rule['location'] ?? 'query'; // 'query' oder 'body'

            $value = $location === 'body' ? ($bodyParams[$param] ?? null) : ($queryParams[$param] ?? null);

            if ($isRequired && $value === null) {
                throw new ApiInvalidArgumentException("Parameter '{$param}' is required in {$location}.");
            }
        }
    }
}
