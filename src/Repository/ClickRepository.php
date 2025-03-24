<?php

namespace LukaLtaApi\Repository;

use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Tracking\Click;
use LukaLtaApi\Value\Tracking\Clicks;
use PDO;
use PDOException;

class ClickRepository
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function recordClick(Click $click): void
    {
        $sql = <<<SQL
            INSERT INTO url_clicks (url, click_tag, clicked_at, ip_address, user_agent, referrer)
            VALUES (:url, :click_tag, :click_date, :ip_address, :user_agent, :referrer)
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'url' => (string)$click->getUrl(),
                'click_tag' => $click->getTag()->getValue(),
                'click_date' => $click->getClickedAt()?->format('Y-m-d H:i:s'),
                'ip_address' => $click->getIpAdress(),
                'user_agent' => $click->getUserAgent(),
                'referrer' => $click->getReferer(),
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to insert click',
                $exception->getCode(),
                $exception
            );
        }
    }

    public function getAll(): Clicks
    {
        $sql = <<<SQL
            SELECT * FROM url_clicks
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute();

            $clicks = [];
            foreach ($statement as $row) {
                $clicks[] = Click::fromDatabase($row);
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to fetch clicks',
                previous: $exception
            );
        }

        return Clicks::from(...$clicks);
    }
}
