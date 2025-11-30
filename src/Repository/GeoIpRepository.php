<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use GuzzleHttp\Client;
use LukaLtaApi\Value\GeoLocation;

class GeoIpRepository
{
    public function __construct(
        private readonly Client $client,
    ) {}

    public function getCountryCodeOfIp(array $ips): GeoLocation
    {
        $response = $this->client->get(sprintf('http://ipwho.is/%s', implode(',', $ips)));

        $rawBody = $response->getBody()->getContents();
        $parsedBody = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);

        return GeoLocation::fromGeoApi($parsedBody);
    }
}
