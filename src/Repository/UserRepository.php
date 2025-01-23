<?php

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
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
    ) {
        $this->pdo->beginTransaction();
    }

    public function create(User $user): void
    {
        $sql = <<<SQL
            INSERT INTO users (email, password, avatar_url)
            VALUES (:email, :password, :avatar_url)
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'email' => $user->getEmail()->getEmail(),
                'password' => $user->getPassword()->getPassword(),
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
            SET email = :email, password = :password, avatar_url = :avatar_url
            WHERE user_id = :user_id
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'email' => $user->getEmail()->getEmail(),
                'password' => $user->getPassword()->getPassword(),
                'avatar_url' => $user->getAvatarUrl(),
                'user_id' => $user->getUserId()?->asInt(),
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

    public function findByEmail(UserEmail $email): ?User
    {
        $sql = <<<SQL
            SELECT *
            FROM users
            WHERE email = :email
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute(['email' => $email->getEmail()]);

            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new ApiDatabaseException(
                'Failed to find user by email',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }

        if ($row === false) {
            return null;
        }

        return User::fromDatabase($row);
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

        if ($row === false) {
            return null;
        }

        return User::fromDatabase($row);
    }

    public function getAll(): Users
    {
        $sql = <<<SQL
            SELECT * FROM users
        SQL;

        try {
            $statement = $this->pdo->query($sql);

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
}
