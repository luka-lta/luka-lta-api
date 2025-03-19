<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Preview\PreviewToken;
use PDO;
use PDOException;

class PreviewTokenRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function createToken(PreviewToken $token): void
    {
        $sql = <<<SQL
            INSERT INTO preview_access_tokens (token, created_by, created_at)
            VALUES (:token, :created_by, :created_at)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'token' => $token->getToken(),
                'created_by' => $token->getUserId()->asInt(),
                'created_at' => $token->getCreatedAt()?->format('Y-m-d H:i:s'),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to create preview token',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }
}
