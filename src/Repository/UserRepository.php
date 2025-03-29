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
use function DI\value;

class UserRepository
{
    public function __construct(
        private readonly PDO          $pdo,
        private readonly QueryFactory $queryFactory,
    ) {
        $this->pdo->beginTransaction();
    }

    public function create(User $user): void
    {
        $sql = <<<SQL
            INSERT INTO users (email, role_id, password, avatar_url)
            VALUES (:email, :role_id, :password, :avatar_url)
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'role_id' => 1,
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
            SELECT 
                u.*,
                ur.role_id,
                ur.role_name AS role_name,
                p.permission_id AS permission_id,
                p.permission_name AS permission_name,
            p.permission_description AS permission_description
            FROM users u
            LEFT JOIN user_roles ur ON u.role_id = ur.role_id
            LEFT JOIN role_permissions rp ON ur.role_id = rp.role_id
            LEFT JOIN permissions p ON rp.permission_id = p.permission_id
            WHERE u.email = :email
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute(['email' => $email->getEmail()]);

            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new ApiDatabaseException(
                'Failed to find user by email',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }

        if (empty($rows)) {
            return null;
        }


        $userData = $this->groupPermissionData($rows);
        return User::fromDatabase($userData);
    }

    public function findById(UserId $userId): ?User
    {
        $sql = <<<SQL
            SELECT 
                u.*,
                ur.role_id,
                ur.role_name AS role_name,
                p.permission_id AS permission_id,
                p.permission_name AS permission_name,
            p.permission_description AS permission_description
            FROM users u
            LEFT JOIN user_roles ur ON u.role_id = ur.role_id
            LEFT JOIN role_permissions rp ON ur.role_id = rp.role_id
            LEFT JOIN permissions p ON rp.permission_id = p.permission_id
            WHERE u.user_id = :user_id
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute(['user_id' => $userId->asInt()]);

            $rows = $statement->fetchAll();
        } catch (PDOException $e) {
            throw new ApiDatabaseException(
                'Failed to find user by id',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $e
            );
        }

        if (empty($rows)) {
            return null;
        }

        $userData = $this->groupPermissionData($rows);
        return User::fromDatabase($userData);
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

    private function groupPermissionData(array $rows): array
    {
        $userData = $rows[0];

        $permissions = [];
        foreach ($rows as $row) {
            if ($row['permission_id'] !== null) {
                $permissions[] = [
                    'permission_id' => $row['permission_id'],
                    'permission_name' => $row['permission_name'],
                    'permission_description' => $row['permission_description']
                ];
            }
        }

        $userData['role'] = $row['role_id'] ? [
            'role_id' => $row['role_id'],
            'role' => $row['role_name'],
            'permissions' => $permissions
        ] : null;

        return $userData;
    }
}
