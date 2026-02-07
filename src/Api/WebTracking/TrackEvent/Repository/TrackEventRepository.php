<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\WebTracking\TrackEvent\Repository;

use Fig\Http\Message\StatusCodeInterface;
use LukaLtaApi\Exception\ApiDatabaseException;
use LukaLtaApi\Value\WebTracking\Tracking\PageViewData;
use PDO;
use PDOException;

class TrackEventRepository
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function insertEvent(PageViewData $pageViewData): void
    {
        $sql = <<<SQL
        INSERT INTO events (
            site_id,
            occurred_on,
            session_id,
            user_id,
            hostname,
            pathname,
            url_parameters,
            page_title,
            referrer,
            channel,
            browser,
            browser_version,
            os,
            os_version,
            language,
            country,
            region,
            city,
            lat,
            lon,
            screen_width,
            screen_height,
            device_type,
            type,
            event_name,
            props
        ) VALUES (
            :site_id,
            :occurred_on,
            :session_id,
            :user_id,
            :hostname,
            :pathname,
            :url_parameters,
            :page_title,
            :referrer,
            :channel,
            :browser,
            :browser_version,
            :os,
            :os_version,
            :language,
            :country,
            :region,
            :city,
            :lat,
            :lon,
            :screen_width,
            :screen_height,
            :device_type,
            :type,
            :event_name,
            :props
        )
    SQL;

        try {
            $stmt = $this->pdo->prepare($sql);

            $stmt->execute([
                'site_id'          => $pageViewData->getSiteId(),
                'occurred_on'      => $pageViewData->getOccurredOn()->format(DATE_ATOM),
                'session_id'       => $pageViewData->getSessionId(),
                'user_id'          => $pageViewData->getUserId(),
                'hostname'         => $pageViewData->getPageInfo()->getHostname(),
                'pathname'         => $pageViewData->getPageInfo()->getPathName(),
                'url_parameters'   => json_encode($pageViewData->getUrlParameters()->toArray(), JSON_THROW_ON_ERROR),
                'page_title'       => $pageViewData->getPageInfo()->getPageTitle(),
                'referrer'         => $pageViewData->getReferrer(),
                'channel'          => $pageViewData->getChannel(),
                'browser'          => $pageViewData->getUserAgent()->getBrowserName(),
                'browser_version'  => $pageViewData->getUserAgent()->getBrowserVersion(),
                'os'               => $pageViewData->getUserAgent()->getOsName(),
                'os_version'       => $pageViewData->getUserAgent()->getOsVersion(),
                'language'         => $pageViewData->getLanguage(),
                'country'          => $pageViewData->getGeoLocation()?->getCountryCode(),
                'region'           => $pageViewData->getGeoLocation()?->getRegionCode(),
                'city'             => $pageViewData->getGeoLocation()?->getCity(),
                'lat'              => $pageViewData->getGeoLocation()?->getLatitude(),
                'lon'              => $pageViewData->getGeoLocation()?->getLongitude(),
                'screen_width'     => $pageViewData->getScreenDimensions()?->getWidth(),
                'screen_height'    => $pageViewData->getScreenDimensions()?->getHeight(),
                'device_type'      => $pageViewData->getDeviceType()?->getDeviceType(),
                'type'             => $pageViewData->getEventType()->getValue(),
                'event_name'       => $pageViewData->getEventName(),
                'props'            => $pageViewData->getProps()
                    ? json_encode($pageViewData->getProps()->getValue(), JSON_THROW_ON_ERROR)
                    : null,
            ]);
        } catch (PDOException $exception) {
            throw new ApiDatabaseException(
                'Failed inserting Web-Tracking Event',
                StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR,
                $exception
            );
        }
    }
}
