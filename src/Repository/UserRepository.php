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
        $this->pdo->beginTransaction();
    }

    public function create(User $user): void
    {
        $sql = <<<SQL
            INSERT INTO users (email, username, password, role, avatar_url)
            VALUES (:email, :username, :password, :role, :avatar_url)
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'email' => $user->getEmail()->getEmail(),
                'username' => $user->getUsername(),
                'password' => $user->getPassword()->getPassword(),
                'role' => $user->getRole()->getRoleId(),
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
                role = :role,
                avatar_url = :avatar_url,
                is_active = :is_active,
                last_active = :last_active
            WHERE user_id = :user_id
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'username' => $user->getUsername(),
                'email' => $user->getEmail()->getEmail(),
                'password' => $user->getPassword()->getPassword(),
                'avatar_url' => $user->getAvatarUrl(),
                'role' => $user->getRole()->getRoleId(),
                'user_id' => $user->getUserId()?->asInt(),
                'is_active' => $user->isActive(),
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

    public function findByEmail(UserEmail $email): ?User
    {
        $sql = <<<SQL
            SELECT 
                u.*,
                JSON_OBJECT(
                    'role_id', r.role_id,
                    'role_name', r.role_name,
                    'permissions', (
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'permission_id', p.permission_id,
                                'permission_name', p.permission_name,
                                'permission_description', p.permission_description
                            )
                        )
                        FROM role_permissions rp
                        JOIN permissions p ON rp.permission_id = p.permission_id
                        WHERE rp.role_id = r.role_id
                    )
                ) AS role_data
            FROM users u
            JOIN user_roles r ON u.role = r.role_id
            WHERE u.email = :email
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
        SELECT 
            u.*,
            JSON_OBJECT(
                'role_id', r.role_id,
                'role_name', r.role_name,
                'permissions', (
                    SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'permission_id', p.permission_id,
                            'permission_name', p.permission_name,
                            'permission_description', p.permission_description
                        )
                    )
                    FROM role_permissions rp
                    JOIN permissions p ON rp.permission_id = p.permission_id
                    WHERE rp.role_id = r.role_id
                )
            ) AS role_data
        FROM users u
        JOIN user_roles r ON u.role = r.role_id
        WHERE u.user_id = :user_id
        GROUP BY u.user_id
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

    public function findByUsername(string $username): ?User
    {
        $sql = <<<SQL
        SELECT 
            u.*,
            JSON_OBJECT(
                'role_id', r.role_id,
                'role_name', r.role_name,
                'permissions', (
                    SELECT JSON_ARRAYAGG(
                        JSON_OBJECT(
                            'permission_id', p.permission_id,
                            'permission_name', p.permission_name,
                            'permission_description', p.permission_description
                        )
                    )
                    FROM role_permissions rp
                    JOIN permissions p ON rp.permission_id = p.permission_id
                    WHERE rp.role_id = r.role_id
                )
            ) AS role_data
        FROM users u
        JOIN user_roles r ON u.role = r.role_id
        WHERE u.username = :username
        GROUP BY u.user_id
    SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute(['username' => $username]);

            $row = $statement->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new ApiDatabaseException(
                'Failed to find user by username',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }

        if ($row === false) {
            return null;
        }

        return User::fromDatabase($row);
    }

    public function getAll(UserExtraFilter $filter): Users
    {
        $select = $this->queryFactory->select('*')->from('users');

        $query = $filter->createSqlFilter($select);
        $sql = $query->compile();

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
}
