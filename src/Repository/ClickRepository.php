<?php

namespace LukaLtaApi\Repository;

use DateTimeImmutable;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Tracking\Click;
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

    public function listAll(DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
    {
        $sql = <<<SQL
        SELECT 
            lc.displayname,
            DATE(uc.clicked_at) as click_date, 
            COUNT(*) as total_clicks
        FROM url_clicks uc
        JOIN link_collection lc ON uc.click_tag = lc.click_tag
        WHERE uc.clicked_at BETWEEN :startDate AND :endDate
        GROUP BY lc.displayname, click_date
        ORDER BY click_date , lc.displayname
    SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'startDate' => $startDate->format('Y-m-d 00:00:00'),
                'endDate' => $endDate->format('Y-m-d 23:59:59'),
            ]);

            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to fetch clicks',
                previous: $exception
            );
        }
    }
}
