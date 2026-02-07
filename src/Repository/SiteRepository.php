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

    public function updateSite(
        int $siteId,
        string $name,
        string $domain,
        bool $isPublic,
        bool $isBlockBots,
        array $excludedIps,
        array $excludedCountries,
        bool $isTrackIps,
        SiteConfig $siteConfig,
    ): void {
        $sql = <<<SQL
            UPDATE 
                sites 
            SET
                 name = :name,
                 domain = :domain,
                 public = :public,
                 block_bots = :blockBots,
                 excluded_ips = :excludedIps,
                 excluded_countries = :excludedCountries,
                 web_vitals = :webVitals,
                 track_errors = :trackErrors,
                 track_outbound  = :trackOutbound,
                 track_url_params = :trackUrlParams,
                 track_initial  = :trackInitial,
                 track_spa_navigation = :trackSpaNavigation,
                 track_ip = :trackIp
            WHERE 
                site_id = :siteId        
        SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'name' => $name,
                'domain' => $domain,
                'public' => $isPublic,
                'blockBots' => $isBlockBots,
                'excludedIps' => json_encode($excludedIps, JSON_THROW_ON_ERROR),
                'excludedCountries' => json_encode($excludedCountries, JSON_THROW_ON_ERROR),
                'webVitals' => $siteConfig->isWebVitals(),
                'trackErrors' => $siteConfig->isTrackErrors(),
                'trackOutbound' => $siteConfig->isTrackOutbound(),
                'trackUrlParams' => $siteConfig->isTrackUrlParams(),
                'trackInitial' => $siteConfig->isTrackInitialPageView(),
                'trackSpaNavigation' => $siteConfig->isTrackSpaNavigation(),
                'trackIp' => $isTrackIps,
                'siteId' => $siteId,
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed to update site',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }
}
