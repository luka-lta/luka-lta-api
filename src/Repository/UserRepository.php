<?php

namespace LukaLtaApi\Repository;

use LukaLtaApi\Value\User\User;
use LukaLtaApi\Value\User\UserEmail;
use PDO;
use PDOException;
use RuntimeException;

class UserRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function createUser(User $user): void
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
        } catch (PDOException $e) {
            throw new RuntimeException('Failed to create user', 0, $e);
        }
    }

    public function findUserByEmail(UserEmail $email): ?User
    {
        $sql = <<<SQL
            SELECT *
            FROM users
            WHERE email = :email
        SQL;

        $statement = $this->pdo->prepare($sql);
        $statement->execute(['email' => $email]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return User::fromDatabase($row);
    }
}
