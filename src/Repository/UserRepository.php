<?php

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use Latitude\QueryBuilder\QueryFactory;
use LukaLtaApi\Api\User\Value\UserExtraFilter;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\User\User;
use LukaLtaApi\Value\User\UserEmail;
use LukaLtaApi\Value\User\UserId;
use LukaLtaApi\Value\User\Users;
use PDO;
use PDOException;

class UserRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly QueryFactory $queryFactory,
    ) {
    }

    public function create(User $user): void
    {
        $sql = <<<SQL
            INSERT INTO users (email, username, password, avatar_url)
            VALUES (:email, :username, :password, :avatar_url)
        SQL;

        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'email' => $user->getEmail()->asString(),
                'username' => $user->getUsername(),
                'password' => $user->getPassword()->asString(),
                'avatar_url' => $user->getAvatarUrl(),
            ]);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new ApiDatabaseException(
                'Failed to create user',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }
    }

    public function update(User $user): void
    {
        $sql = <<<SQL
            UPDATE users
            SET 
                username = :username, 
                email = :email, 
                password = :password,
                avatar_url = :avatar_url,
                is_active = :is_active,
                last_active = :last_active
            WHERE user_id = :user_id
        SQL;

        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'username' => $user->getUsername(),
                'email' => $user->getEmail()->asString(),
                'password' => $user->getPassword()->asString(),
                'avatar_url' => $user->getAvatarUrl(),
                'user_id' => $user->getUserId()?->asInt(),
                'is_active' => (int)$user->isActive(),
                'last_active' => $user->getLastActive()?->format('Y-m-d H:i:s'),
            ]);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw new ApiDatabaseException(
                'Failed to update user',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }
    }

    public function findByEmail(UserEmail $email, ?UserId $excludeUserId = null): ?User
    {
        $sql = <<<SQL
            SELECT * FROM users
            WHERE email = :email
        SQL;

        if ($excludeUserId !== null) {
            $sql .= ' AND user_id != :exclude_id';
        }

        try {
            $statement = $this->pdo->prepare($sql);
            $params = ['email' => $email->asString()];
            if ($excludeUserId !== null) {
                $params['exclude_id'] = $excludeUserId->asInt();
            }
            $statement->execute($params);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new ApiDatabaseException(
                'Failed to find user by email',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }

        return $row !== false ? User::fromDatabase($row) : null;
    }

    public function findById(UserId $userId): ?User
    {
        $sql = <<<SQL
            SELECT *
            FROM users
            WHERE user_id = :user_id
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute(['user_id' => $userId->asInt()]);

            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new ApiDatabaseException(
                'Failed to find user by id',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }

        return $row !== false ? User::fromDatabase($row) : null;
    }

    public function findByUsername(string $username, ?UserId $excludeUserId = null): ?User
    {
        $sql = <<<SQL
            SELECT * FROM users WHERE username = :username
        SQL;

        if ($excludeUserId !== null) {
            $sql .= ' AND user_id != :exclude_id';
        }

        try {
            $statement = $this->pdo->prepare($sql);
            $params = ['username' => $username];
            if ($excludeUserId !== null) {
                $params['exclude_id'] = $excludeUserId->asInt();
            }
            $statement->execute($params);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new ApiDatabaseException(
                'Failed to find user by username',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }

        return $row !== false ? User::fromDatabase($row) : null;
    }

    public function getAll(UserExtraFilter $filter): Users
    {
        $select = $this->queryFactory->select('*')->from('users');
        $query  = $filter->createSqlFilter($select);
        $sql    = $query->compile();

        try {
            $statement = $this->pdo->prepare($sql->sql());
            $statement->execute($sql->params());

            $users = [];
            foreach ($statement as $row) {
                $users[] = User::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to fetch users',
                previous: $exception
            );
        }

        return Users::from(...$users);
    }

    public function deleteUser(UserId $userId): void
    {
        $sql = <<<SQL
            DELETE FROM users WHERE user_id = :user_id
        SQL;

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['user_id' => $userId->asInt()]);
            $this->pdo->commit();
        } catch (PDOException $exception) {
            $this->pdo->rollBack();
            throw new ApiDatabaseException(
                'Failed to delete user',
                previous: $exception
            );
        }
    }
}
