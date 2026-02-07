<?php

declare(strict_types=1);

namespace LukaLtaApi\Service;

class ChannelDetectorService
{
    public function detectChannel(
        ?string $referrer,
        ?string $queryString,
        ?string $hostname,
    ): string {
        $utm       = $this->getUtmParams($queryString ?? '');
        $domain    = $this->getDomainFromReferrer($referrer ?? '');
        $selfRef   = $hostname && $this->isSelfReferral($domain, $hostname);

        $utmSource    = $utm['utm_source'] ?? '';
        $utmMedium    = $utm['utm_medium'] ?? '';
        $utmCampaign  = $utm['utm_campaign'] ?? '';
        $gclid        = $utm['gclid'] ?? '';
        $gadSource    = $utm['gad_source'] ?? '';

        // Intern / Direkt
        if (!$referrer && !$utmSource && !$utmMedium && !$utmCampaign && !$gclid && !$gadSource) {
            return $selfRef ? "Internal" : "Direct";
        }

        // Source / Medium
        $sourceType = $this->getSourceType($utmSource ?: $domain);
        $mediumType = $this->getMediumType($utmMedium);
        $isPaid     = $this->isPaidTraffic($utmMedium, $utmSource) || $gclid || $gadSource;

        // Cross-Network
        if ($utmCampaign === "cross-network") {
            return "Cross-Network";
        }

        // Direct
        if (
            ($domain === '$direct' || (!$referrer && !$selfRef))
            && !$utmMedium
            && (!$utmSource || $utmSource === "direct" || $utmSource === "(direct)")
        ) {
            return "Direct";
        }

        // Paid Traffic
        if ($isPaid) {
            return $this->detectPaidChannel($sourceType, $mediumType);
        }

        // Organic Traffic
        if ($organic = $this->detectOrganic($sourceType)) {
            return $organic;
        }

        // Medium-basierte Organic
        if ($organic = $this->mediumOrganicFallback($mediumType)) {
            return $organic;
        }

        // Campaign-basierte Fallbacks
        if ($campaign = $this->campaignFallback($utmCampaign)) {
            return $campaign;
        }

        // Referral
        if ($domain && $domain !== '$direct' && !$selfRef) {
            return "Referral";
        }

        return "Unknown";
    }

    private function getUtmParams(string $query): array
    {
        parse_str($query, $params);
        return $params;
    }

    private function getDomainFromReferrer(string $referrer): string
    {
        if (!$referrer) return '$direct';
        $host = parse_url($referrer, PHP_URL_HOST);
        return $host ?: '$direct';
    }

    private function isSelfReferral(string $refDomain, string $hostname): bool
    {
        return $refDomain === $hostname;
    }

    private function getSourceType(string $source): string
    {
        $source = strtolower($source);

        return match (true) {
            str_contains($source, 'google'),
            str_contains($source, 'bing')         => 'search',
            str_contains($source, 'facebook'),
            str_contains($source, 'instagram'),
            str_contains($source, 'tiktok')       => 'social',
            str_contains($source, 'youtube')      => 'video',
            default                               => 'unknown'
        };
    }

    private function getMediumType(string $medium): string
    {
        return strtolower($medium);
    }

    private function isPaidTraffic(string $medium): bool
    {
        $medium = strtolower($medium);
        return in_array($medium, ['cpc', 'ppc', 'paid', 'ads'], true);
    }

    private function detectPaidChannel(string $sourceType, string $mediumType): string
    {
        return match ($sourceType) {
            'search'   => 'Paid Search',
            'social'   => 'Paid Social',
            'video'    => 'Paid Video',
            'shopping' => 'Paid Shopping',
            default => match ($mediumType) {
                'social'     => 'Paid Social',
                'video'      => 'Paid Video',
                'display',
                'cpm'        => 'Display',
                'cpc'        => 'Paid Search',
                'influencer' => 'Paid Influencer',
                'audio'      => 'Paid Audio',
                default      => 'Paid Unknown',
            }
        };
    }

    private function detectOrganic(string $sourceType): ?string
    {
        return match ($sourceType) {
            'search'      => 'Organic Search',
            'social'      => 'Organic Social',
            'video'       => 'Organic Video',
            'shopping'    => 'Organic Shopping',
            'email'       => 'Email',
            'sms'         => 'SMS',
            'news'        => 'News',
            'productivity'=> 'Productivity',
            default       => null
        };
    }

    private function mediumOrganicFallback(string $mediumType): ?string
    {
        return match ($mediumType) {
            'social'     => 'Organic Social',
            'video'      => 'Organic Video',
            'affiliate'  => 'Affiliate',
            'referral'   => 'Referral',
            'display'    => 'Display',
            'audio'      => 'Audio',
            'push'       => 'Push',
            'influencer' => 'Influencer',
            'content'    => 'Content',
            'event'      => 'Event',
            'email'      => 'Email',
            default      => null,
        };
    }

    private function campaignFallback(string $campaign): ?string
    {
        if (!$campaign) return null;
        $c = strtolower($campaign);

        return match (true) {
            str_contains($c, 'video')        => 'Organic Video',
            str_contains($c, 'shop'),
            str_contains($c, 'shopping')     => 'Organic Shopping',
            str_contains($c, 'influencer'),
            str_contains($c, 'creator'),
            str_contains($c, 'sponsored')     => 'Influencer',
            str_contains($c, 'event'),
            str_contains($c, 'conference'),
            str_contains($c, 'webinar')       => 'Event',
            str_contains($c, 'social'),
            str_contains($c, 'facebook'),
            str_contains($c, 'twitter'),
            str_contains($c, 'instagram'),
            str_contains($c, 'linkedin')      => 'Organic Social',
            default                           => null
        };
    }
}
