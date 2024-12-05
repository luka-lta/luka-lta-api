<?php

namespace LukaLtaApi\Api;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiInvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

class RequestValidator
{
    public const string LOCATION_QUERY = 'query';
    public const string LOCATION_BODY = 'body';

    public function validate(
        ServerRequestInterface $request,
        array $rules
    ): void {
        $queryParams = $request->getQueryParams();
        $bodyParams = $request->getParsedBody();

        foreach ($rules as $param => $rule) {
            $isRequired = $rule['required'] ?? false;
            $location = $rule['location'] ?? self::LOCATION_QUERY; // 'query' oder 'body'

            $value = $location === self::LOCATION_BODY ? ($bodyParams[$param] ?? null) : ($queryParams[$param] ?? null);

            if ($isRequired && $value === null) {
                throw new ApiInvalidArgumentException(
                    "Parameter '{$param}' is required in {$location}.",
                    StatusCodeInterface::STATUS_BAD_REQUEST
                );
            }
        }
    }
}
