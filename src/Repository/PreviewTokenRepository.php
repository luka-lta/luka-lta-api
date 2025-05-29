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
                'created_by' => $token->getCreatedBy()->getUserId()?->asInt(),
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
            SELECT 
                pat.token,
                pat.max_uses,
                pat.uses,
                pat.is_active,
                pat.created_by,
                pat.created_at,
                COALESCE(
                    NULLIF(
                        JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'user_id', u.user_id,
                                'username', u.username,
                                'email', u.email,
                                'password', u.password,
                                'avatar_url', u.avatar_url,
                                'is_active', u.is_active,
                                'last_active', u.last_active,
                                'created_at', u.created_at,
                                'updated_at', u.updated_at
                            )
                        ),
                    JSON_ARRAY(NULL)
                    ),
                    JSON_ARRAY()
                    ) AS user
            FROM preview_access_tokens as pat
            LEFT JOIN users u on u.user_id = pat.created_by
            GROUP BY pat.token
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

    public function updateToken(PreviewToken $token): void
    {
        $sql = <<<SQL
            UPDATE preview_access_tokens
            SET uses = :uses, is_active = :is_active, max_uses = :max_uses
            WHERE token = :token
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'uses' => $token->getUsed(),
                'token' => $token->getToken(),
                'is_active' => $token->isActive(),
                'max_uses' => $token->getMaxUse(),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to update preview token',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }

    public function getToken(string $tokenId): ?PreviewToken
    {
        $sql = <<<SQL
            SELECT 
                pat.token,
                pat.max_uses,
                pat.uses,
                pat.is_active,
                pat.created_by,
                pat.created_at,
                COALESCE(
                    NULLIF(
                        JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'user_id', u.user_id,
                                'username', u.username,
                                'email', u.email,
                                'password', u.password,
                                'avatar_url', u.avatar_url,
                                'is_active', u.is_active,
                                'last_active', u.last_active,
                                'created_at', u.created_at,
                                'updated_at', u.updated_at
                            )
                        ),
                    JSON_ARRAY(NULL)
                    ),
                    JSON_ARRAY()
                    ) AS user
            FROM preview_access_tokens as pat
            LEFT JOIN users u on u.user_id = pat.created_by
            WHERE pat.token = :token
            GROUP BY pat.token
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['token' => $tokenId]);

            $row = $stmt->fetch();

            if ($row === false) {
                return null;
            }

            return PreviewToken::fromDatabase($row);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to get preview token',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }

    public function deleteToken(string $tokenId): void
    {
        $sql = <<<SQL
            DELETE FROM preview_access_tokens WHERE token = :token
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['token' => $tokenId]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to delete preview token',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }
}
