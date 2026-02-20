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
    case DEVICE_TYPE = 'device_type';
    case REFERRER = 'referrer';
    case HOSTNAME = 'hostname';
    case PATHNAME = 'pathname';
    case PAGE_TITLE = 'page_title';
    case QUERYSTRING = 'url_parameters';
    case EVENT_NAME = 'event_name';
    case CHANNEL = 'channel';
    case UTM_SOURCE = 'utm_source';
    case UTM_MEDIUM = 'utm_medium';
    case UTM_CAMPAIGN = 'utm_campaign';
    case UTM_TERM = 'utm_term';
    case UTM_CONTENT = 'utm_content';
    case ENTRY_PAGE = 'entry_page';
    case EXIT_PAGE = 'exit_page';
    case DIMENSIONS = 'dimensions';
    case BROWSER_VERSION = 'browser_version';
    case OS_VERSION = 'os_version';
    case USER_ID = 'user_id';
    case LAT = 'lat';
    case LON = 'lon';
    case TIMEZONE = 'timezone';
    case VPN = 'vpn';
    case CRAWLER = 'crawler';
    case DATACENTER = 'datacenter';
    case COMPANY = 'company';
    case COMPANY_TYPE = 'company_type';
    case COMPANY_DOMAIN = 'company_domain';
    case ASN_ORG = 'asn_org';
    case ASN_TYPE = 'asn_type';
    case ASN_DOMAIN = 'asn_domain';

    public static function fromName(string $name): self
    {
        return match ($name) {
            'browser' => self::BROWSER,
            'os' => self::OPERATING_SYSTEM,
            'language' => self::LANGUAGE,
            'country' => self::COUNTRY,
            'region' => self::REGION,
            'city' => self::CITY,
            'device_type' => self::DEVICE_TYPE,
            'referrer' => self::REFERRER,
            'hostname' => self::HOSTNAME,
            'pathname' => self::PATHNAME,
            'page_title' => self::PAGE_TITLE,
            'url_parameters' => self::QUERYSTRING,
            'event_name' => self::EVENT_NAME,
            'channel' => self::CHANNEL,
            'utm_source' => self::UTM_SOURCE,
            'utm_medium' => self::UTM_MEDIUM,
            'utm_campaign' => self::UTM_CAMPAIGN,
            'utm_term' => self::UTM_TERM,
            'utm_content' => self::UTM_CONTENT,
            'entry_page' => self::ENTRY_PAGE,
            'exit_page' => self::EXIT_PAGE,
            'dimensions' => self::DIMENSIONS,
            'browser_version' => self::BROWSER_VERSION,
            'os_version' => self::OS_VERSION,
            'user_id' => self::USER_ID,
            'lat' => self::LAT,
            'lon' => self::LON,
            'timezone' => self::TIMEZONE,
            'vpn' => self::VPN,
            'crawler' => self::CRAWLER,
            'datacenter' => self::DATACENTER,
            'company' => self::COMPANY,
            'company_type' => self::COMPANY_TYPE,
            'company_domain' => self::COMPANY_DOMAIN,
            'asn_org' => self::ASN_ORG,
            'asn_type' => self::ASN_TYPE,
            'asn_domain' => self::ASN_DOMAIN,
            default => throw new InvalidArgumentException(sprintf('Invalid metric parameter: %s', $name)),
        };
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
