<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use LukaLtaApi\Exception\ApiHttpException;
use LukaLtaApi\Value\GeoLocation;

class GeoLocationRepository
{
    public function __construct(
        private readonly Client $client,
    ) {
    }

    public function findByIp(string $ipAddress): GeoLocation
    {
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
            $ipAddress = '8.8.8.8';
        }

        try {
            $response = $this->client->request(
                'GET',
                sprintf('https://ipwho.is/%s', $ipAddress),
                [
                    'timeout' => 5,
                ]
            );
        } catch (GuzzleException $exception) {
            throw new ApiHttpException(
                'Geo API request failed',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception,
            );
        }


        $data = json_decode((string)$response->getBody(), true);

        if (!is_array($data)) {
            throw new ApiHttpException(
                'Invalid response from geo api',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
            );
        }

        if (($data['success'] ?? false) !== true) {
            throw new ApiHttpException(
                $data['message'] ?? 'Geo API request failed',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
            );
        }

        return GeoLocation::fromGeoApi($data);
    }
}
