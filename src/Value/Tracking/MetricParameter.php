<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Tracking;

use InvalidArgumentException;

enum MetricParameter: string
{
    case BROWSER = 'browser';
    case OPERATING_SYSTEM = 'os';
    case LANGUAGE = 'language';
    case COUNTRY = 'country';
    case REGION = 'region';
    case CITY = 'city';
    case DEVICE_TYPE = 'device';
    case REFERRER = 'referrer';
    case HOSTNAME = 'hostname';
    case PATHNAME = 'pathname';
    case PAGE_TITLE = 'pageTitle';
    case QUERYSTRING = 'querystring';
    case EVENT_NAME = 'eventName';
    case CHANNEL = 'channel';
    case UTM_SOURCE = 'utmSource';
    case UTM_MEDIUM = 'utmMedium';
    case UTM_CAMPAIGN = 'utmCampaign';
    case UTM_TERM = 'utmTerm';
    case UTM_CONTENT = 'utmContent';
    case ENTRY_PAGE = 'entryPage';
    case EXIT_PAGE = 'exitPage';
    case DIMENSIONS = 'dimensions';
    case BROWSER_VERSION = 'browserVersion';
    case OS_VERSION = 'osVersion';
    case USER_ID = 'userId';
    case LAT = 'lat';
    case LON = 'lon';
    case TIMEZONE = 'timezone';
    case VPN = 'vpn';
    case CRAWLER = 'crawler';
    case DATACENTER = 'datacenter';
    case COMPANY = 'company';
    case COMPANY_TYPE = 'companyType';
    case COMPANY_DOMAIN = 'companyDomain';
    case ASN_ORG = 'asnOrg';
    case ASN_TYPE = 'asnType';
    case ASN_DOMAIN = 'asnDomain';

    public static function fromName(string $name): self
    {
        return match ($name) {
            'browser' => self::BROWSER,
            'os' => self::OPERATING_SYSTEM,
            'language' => self::LANGUAGE,
            'country' => self::COUNTRY,
            'region' => self::REGION,
            'city' => self::CITY,
            'device' => self::DEVICE_TYPE,
            'referrer' => self::REFERRER,
            'hostname' => self::HOSTNAME,
            'pathname' => self::PATHNAME,
            'pageTitle' => self::PAGE_TITLE,
            'querystring' => self::QUERYSTRING,
            'eventName' => self::EVENT_NAME,
            'channel' => self::CHANNEL,
            'utmSource' => self::UTM_SOURCE,
            'utmMedium' => self::UTM_MEDIUM,
            'utmCampaign' => self::UTM_CAMPAIGN,
            'utmTerm' => self::UTM_TERM,
            'utmContent' => self::UTM_CONTENT,
            'entryPage' => self::ENTRY_PAGE,
            'exitPage' => self::EXIT_PAGE,
            'dimensions' => self::DIMENSIONS,
            'browserVersion' => self::BROWSER_VERSION,
            'osVersion' => self::OS_VERSION,
            'user_id' => self::USER_ID,
            'lat' => self::LAT,
            'lon' => self::LON,
            'timezone' => self::TIMEZONE,
            'vpn' => self::VPN,
            'crawler' => self::CRAWLER,
            'datacenter' => self::DATACENTER,
            'company' => self::COMPANY,
            'companyType' => self::COMPANY_TYPE,
            'companyDomain' => self::COMPANY_DOMAIN,
            'asnOrg' => self::ASN_ORG,
            'asnType' => self::ASN_TYPE,
            'asnDomain' => self::ASN_DOMAIN,
            default => throw new InvalidArgumentException(sprintf('Invalid metric parameter: %s', $name)),
        };
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
