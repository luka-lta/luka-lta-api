<?php

declare(strict_types=1);

namespace LukaLtaApi\App\Factory;

use Aws\Credentials\CredentialProvider;
use Aws\S3\S3Client;
use LukaLtaApi\Repository\EnvironmentRepository;
use Psr\Container\ContainerInterface;

class MinIOFactory
{
    private const string ENV_NAME_S3_VERSION = 'AWS_VERSION';
    private const string ENV_NAME_S3_REGION = 'AWS_REGION';
    private const string ENV_NAME_S3_ENDPOINT = 'AWS_ENDPOINT';

    public function __invoke(ContainerInterface $container): S3Client
    {
        /** @var EnvironmentRepository $envRepository */
        $envRepository = $container->get(EnvironmentRepository::class);

        return new S3Client(
            [
                'version' => $envRepository->get(self::ENV_NAME_S3_VERSION),
                'region' => $envRepository->get(self::ENV_NAME_S3_REGION),
                'credentials' => CredentialProvider::env(),
                'endpoint' => $envRepository->get(self::ENV_NAME_S3_ENDPOINT),
                'use_path_style_endpoint' => true,
            ]
        );
    }
}
