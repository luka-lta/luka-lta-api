<?php

declare(strict_types=1);

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Preview\PreviewToken;
use LukaLtaApi\Value\Preview\PreviewTokens;
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
            INSERT INTO preview_access_tokens (token, max_uses, is_active, created_by, created_at)
            VALUES (:token, :max_uses, :is_active, :created_by, :created_at)
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'token' => $token->getToken(),
                'max_uses' => $token->getMaxUse(),
                'is_active' => $token->isActive(),
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

    public function listTokens(): PreviewTokens
    {
        $sql = <<<SQL
            SELECT token, max_uses, uses, is_active, created_by, created_at
            FROM preview_access_tokens
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            $tokens = [];

            foreach ($stmt as $row) {
                $tokens[] = PreviewToken::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to list preview tokens',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }

        return PreviewTokens::from(...$tokens);
    }
}
