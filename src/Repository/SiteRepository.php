<?php

namespace LukaLtaApi\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\WebTracking\Config\SiteConfig;
use LukaLtaApi\Value\WebTracking\Site\Site;
use PDO;
use PDOException;

class SiteRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function getSite(int $siteId): ?Site
    {
        $sql = <<<SQL
            SELECT * FROM sites WHERE site_id = :siteId
        SQL;


        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'siteId' => $siteId,
            ]);

            $site = $stmt->fetch();

            if (!$site) {
                return null;
            }
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to load site',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }

        return Site::fromDatabase($site);
    }

    public function createSiteId(Site $site): int
    {
        $siteConfig = $site->getSiteConfig();
        $sql = "INSERT INTO sites (
                name, domain, created_by, public, block_bots,
                excluded_ips, excluded_countries, web_vitals,
                track_errors, track_outbound, track_url_params,
                track_initial, track_spa_navigation, track_ip,
                track_button_clicks, track_copy, track_form_interactions
            ) VALUES (
                :name, :domain, :created_by, :public, :block_bots,
                :excluded_ips, :excluded_countries, :web_vitals,
                :track_errors, :track_outbound, :track_url_params,
                :track_initial, :track_spa_navigation, :track_ip,
                :track_button_clicks, :track_copy, :track_form_interactions
            )";


        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':name'                    => $site->getName(),
                ':domain'                  => $site->getDomain(),
                ':created_by'              => $site->getCreatedBy()->asString(),
                ':public'                  => (int) $site->isPublic(),
                ':block_bots'              => (int) $site->isBlockBots(),
                ':excluded_ips'            => json_encode($site->getExcludedIps(), JSON_THROW_ON_ERROR),
                ':excluded_countries'      => json_encode($site->getExcludedCountries(), JSON_THROW_ON_ERROR),
                ':web_vitals'              => (int) $siteConfig->isWebVitals(),
                ':track_errors'            => (int) $siteConfig->isTrackErrors(),
                ':track_outbound'          => (int) $siteConfig->isTrackOutbound(),
                ':track_url_params'        => (int) $siteConfig->isTrackUrlParams(),
                ':track_initial'           => (int) $siteConfig->isTrackInitialPageView(),
                ':track_spa_navigation'    => (int) $siteConfig->isTrackSpaNavigation(),
                ':track_ip'                => (int) $site->isTrackIp(),
                ':track_button_clicks'     => (int) $siteConfig->isTrackButtonClicks(),
                ':track_copy'              => (int) $siteConfig->isTrackCopy(),
                ':track_form_interactions' => (int) $siteConfig->isTrackFormInteractions(),
            ]);
            return (int)$this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to create site',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }

    public function updateSite(
        int $siteId,
        array $updateData,
    ): void {
        $set = [];
        $params = ['siteId' => $siteId];

        foreach ($updateData as $column => $value) {
            $param = ':' . $column;
            $set[] = "$column = $param";
            $params[$column] = $value;
        }

        $set[] = 'updated_at = NOW()';

        $sql = sprintf(
            'UPDATE sites SET %s WHERE site_id = :siteId',
            implode(', ', $set)
        );

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to update site',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }
}
