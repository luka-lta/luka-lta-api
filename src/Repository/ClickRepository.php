<?php

namespace LukaLtaApi\Repository;

use DateTimeImmutable;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\Tracking\Click;
use LukaLtaApi\Value\Tracking\Clicks;
use LukaLtaApi\Value\Tracking\ClickSummary;
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
            INSERT INTO url_clicks (url, click_tag, clicked_at, ip_address, market, user_agent, referrer)
            VALUES (:url, :click_tag, :click_date, :ip_address, :market, :user_agent, :referrer)
        SQL;

        try {
            $statement = $this->pdo->prepare($sql);
            $statement->execute([
                'url' => (string)$click->getUrl(),
                'click_tag' => $click->getTag()->getValue(),
                'click_date' => $click->getClickedAt()?->format('Y-m-d H:i:s'),
                'ip_address' => $click->getIpAddress(),
                'market' => $click->getMarket(),
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

    public function getSummary(): ClickSummary
    {
        $queryOverTime = "
        SELECT 
            DATE(uc.clicked_at) AS date,
            lc.displayname,
            COUNT(uc.click_id) AS total_clicks
        FROM url_clicks uc
        JOIN link_collection lc ON uc.click_tag = lc.click_tag
        WHERE uc.clicked_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(uc.clicked_at), lc.displayname
        ORDER BY DATE(uc.clicked_at);
    ";

        $queryOverview = "
        SELECT 
            DATE_FORMAT(uc.clicked_at, '%Y-%m') AS month,
            lc.displayname,
            COUNT(uc.click_id) AS total_clicks
        FROM url_clicks uc
        JOIN link_collection lc ON uc.click_tag = lc.click_tag
        WHERE uc.clicked_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
        GROUP BY DATE_FORMAT(uc.clicked_at, '%Y-%m'), lc.displayname
        ORDER BY DATE_FORMAT(uc.clicked_at, '%Y-%m');
    ";

        $queryTotal = "SELECT COUNT(*) AS totalClicks FROM url_clicks;";

        try {
            $stmtOverTime = $this->pdo->query($queryOverTime);
            $clicksOverTime = $stmtOverTime->fetchAll();

            $stmtOverview = $this->pdo->query($queryOverview);
            $clicksOverview = $stmtOverview->fetchAll();

            $stmtTotal = $this->pdo->query($queryTotal);
            $totalClicks = $stmtTotal->fetch()['totalClicks'];
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to fetch click summary',
                previous: $exception
            );
        }

        return ClickSummary::from($totalClicks, $clicksOverview, $clicksOverTime);
    }

    public function listStats(DateTimeImmutable $startDate, DateTimeImmutable $endDate): array
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

    public function listAll(): Clicks
    {
        $sql = <<<SQL
            SELECT
                lc.displayname,
                uc.click_tag,
                uc.click_id,
                uc.url,
                uc.clicked_at,
                uc.ip_address,
                uc.user_agent,
                uc.referrer,
                uc.market,
                DATE(uc.clicked_at) AS click_date
            FROM url_clicks uc
            JOIN link_collection lc
                ON uc.click_tag = lc.click_tag
            ORDER BY
                click_date, lc.displayname
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
