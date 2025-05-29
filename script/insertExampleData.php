<?php

use LukaLtaApi\App\Factory\ContainerFactory;
use LukaLtaApi\Repository\ApiKeyRepository;
use LukaLtaApi\Repository\UserRepository;
use LukaLtaApi\Value\ApiKey\ApiKeyObject;
use LukaLtaApi\Value\User\User;

require_once __DIR__ . '/../vendor/autoload.php';

$container = ContainerFactory::build();
$pdo = $container->get(PDO::class);
$userRepo = $container->get(UserRepository::class);
$apiKeyRepository = $container->get(ApiKeyRepository::class);

try {
    $user = User::create(
        'test@example.de',
        't.test',
        '1234'
    );

    $userRepo->create($user);
    $userId = $userRepo->findByUsername('t.test');
    $apiKey = ApiKeyObject::create(
        'https://text.example.de',
        $userId->getUserId()->asInt(),
        new DateTimeImmutable(),
        \LukaLtaApi\Value\Permission\Permissions::fromObjects(
        )
    );
    $apiKeyRepository->create($apiKey);
} catch (Exception $exception) {
    echo $exception->getMessage();
}