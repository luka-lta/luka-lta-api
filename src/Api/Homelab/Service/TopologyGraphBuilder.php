<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Service;

use LukaLtaApi\Value\Alert\Alerts;
use LukaLtaApi\Value\Homelab\Containers;
use LukaLtaApi\Value\Homelab\Hosts;
use LukaLtaApi\Value\Homelab\TopologyEdge;
use LukaLtaApi\Value\Homelab\TopologyEdges;
use LukaLtaApi\Value\Homelab\TopologyNodes;

/**
 * Builds the full infrastructure graph purely from observed data (host/container
 * state, Docker network membership, Compose labels, Traefik labels) plus manually
 * curated nodes/edges. Never branches on a specific service name — node "type" and
 * "role" are free-form strings supplied by the agent/user, so new kinds of
 * infrastructure need no code changes here.
 */
class TopologyGraphBuilder
{
    /** Docker network names that are host-local plumbing, not a meaningful relation. */
    private const IGNORED_NETWORK_NAMES = ['bridge', 'host', 'none'];

    public function build(
        Hosts $hosts,
        Containers $containers,
        TopologyNodes $manualNodes,
        TopologyEdges $manualEdges,
        Alerts $activeAlerts,
    ): array {
        $hostIdsWithAlerts      = $this->collectAffectedIds($activeAlerts, 'hostId');
        $containerIdsWithAlerts = $this->collectAffectedIds($activeAlerts, 'containerId');

        $nodes = [
            ...$this->buildHostNodes($hosts, $hostIdsWithAlerts),
            ...$this->buildContainerNodes($containers, $containerIdsWithAlerts),
            ...iterator_to_array($manualNodes),
        ];

        $edges = [
            ...$this->buildHostedOnEdges($containers),
            ...$this->buildNetworkEdges($containers),
            ...$this->buildDependsOnEdges($containers),
            ...$this->buildRoutesToEdges($containers),
            ...iterator_to_array($manualEdges),
        ];

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    private function collectAffectedIds(Alerts $alerts, string $contextKey): array
    {
        $ids = [];
        foreach ($alerts as $alert) {
            $value = $alert->getContext()[$contextKey] ?? null;
            if ($value !== null) {
                $ids[$value] = true;
            }
        }

        return $ids;
    }

    /** @return array[] */
    private function buildHostNodes(Hosts $hosts, array $hostIdsWithAlerts): array
    {
        $nodes = [];
        foreach ($hosts as $host) {
            $nodes[] = [
                'id'       => $host->getHostId(),
                'type'     => $host->getNodeType(),
                'name'     => $host->getName(),
                'status'   => isset($hostIdsWithAlerts[$host->getHostId()]) ? 'warning' : 'healthy',
                'metadata' => (object) [],
                'manual'   => false,
                'source'   => 'host',
            ];
        }

        return $nodes;
    }

    /** @return array[] */
    private function buildContainerNodes(Containers $containers, array $containerIdsWithAlerts): array
    {
        $nodes = [];
        foreach ($containers as $container) {
            $nativeStatus = $this->mapContainerStatus($container->getStatus());
            $hasAlert     = isset($containerIdsWithAlerts[$container->getContainerId()]);
            // An alert can only escalate a healthy container to "warning" —
            // it never downgrades an already worse native status (e.g. offline).
            $status       = $hasAlert && $nativeStatus === 'healthy' ? 'warning' : $nativeStatus;
            $nodes[] = [
                'id'       => $container->getContainerId(),
                'type'     => $container->getNodeRole() ?? 'container',
                'name'     => $container->getName(),
                'status'   => $status,
                'metadata' => (object) [],
                'manual'   => false,
                'source'   => 'container',
            ];
        }

        return $nodes;
    }

    private function mapContainerStatus(string $status): string
    {
        return match ($status) {
            'running' => 'healthy',
            'warning' => 'warning',
            'unhealthy', 'stopped' => 'offline',
            default => 'unknown',
        };
    }

    /** @return TopologyEdge[] */
    private function buildHostedOnEdges(Containers $containers): array
    {
        $edges = [];
        foreach ($containers as $container) {
            $edges[] = TopologyEdge::computed(
                'container',
                $container->getContainerId(),
                'host',
                $container->getHostId(),
                'hosted_on',
            );
        }

        return $edges;
    }

    /** @return TopologyEdge[] */
    private function buildNetworkEdges(Containers $containers): array
    {
        $containerList = iterator_to_array($containers);
        $edges         = [];
        $seenPairs     = [];

        foreach ($containerList as $i => $a) {
            foreach ($containerList as $j => $b) {
                if ($j <= $i || $a->getHostId() !== $b->getHostId()) {
                    continue;
                }

                $sharedNetworks = array_diff(
                    array_intersect($a->getNetworks(), $b->getNetworks()),
                    self::IGNORED_NETWORK_NAMES,
                );

                if (empty($sharedNetworks)) {
                    continue;
                }

                $pairKey = $a->getContainerId() < $b->getContainerId()
                    ? "{$a->getContainerId()}|{$b->getContainerId()}"
                    : "{$b->getContainerId()}|{$a->getContainerId()}";

                if (isset($seenPairs[$pairKey])) {
                    continue;
                }
                $seenPairs[$pairKey] = true;

                $edges[] = TopologyEdge::computed(
                    'container',
                    $a->getContainerId(),
                    'container',
                    $b->getContainerId(),
                    'connects_to',
                    ['networks' => array_values($sharedNetworks)],
                );
            }
        }

        return $edges;
    }

    /** @return TopologyEdge[] */
    private function buildDependsOnEdges(Containers $containers): array
    {
        $byComposeKey = [];
        foreach ($containers as $container) {
            $labels  = $container->getLabels();
            $project = $labels['com.docker.compose.project'] ?? null;
            $service = $labels['com.docker.compose.service'] ?? null;

            if ($project !== null && $service !== null) {
                $byComposeKey["{$project}::{$service}"] = $container;
            }
        }

        $edges = [];
        foreach ($containers as $container) {
            $labels     = $container->getLabels();
            $project    = $labels['com.docker.compose.project'] ?? null;
            $dependsOn  = $labels['com.docker.compose.depends_on'] ?? null;

            if ($project === null || $dependsOn === null || $dependsOn === '') {
                continue;
            }

            foreach (explode(',', $dependsOn) as $dependencyService) {
                $dependencyService = trim(explode(':', $dependencyService)[0]);
                $target             = $byComposeKey["{$project}::{$dependencyService}"] ?? null;

                if ($target !== null) {
                    $edges[] = TopologyEdge::computed(
                        'container',
                        $container->getContainerId(),
                        'container',
                        $target->getContainerId(),
                        'depends_on',
                    );
                }
            }
        }

        return $edges;
    }

    /** @return TopologyEdge[] */
    private function buildRoutesToEdges(Containers $containers): array
    {
        $reverseProxies = [];
        $routed         = [];

        foreach ($containers as $container) {
            if ($container->getNodeRole() === 'reverse-proxy') {
                $reverseProxies[] = $container;
            }

            $rule = $this->findTraefikRule($container->getLabels());
            if ($rule !== null) {
                $routed[] = ['container' => $container, 'rule' => $rule];
            }
        }

        $edges = [];
        foreach ($reverseProxies as $proxy) {
            foreach ($routed as $entry) {
                if ($entry['container']->getContainerId() === $proxy->getContainerId()) {
                    continue;
                }

                $edges[] = TopologyEdge::computed(
                    'container',
                    $proxy->getContainerId(),
                    'container',
                    $entry['container']->getContainerId(),
                    'routes_to',
                    ['rule' => $entry['rule']],
                );
            }
        }

        return $edges;
    }

    private function findTraefikRule(array $labels): ?string
    {
        foreach ($labels as $key => $value) {
            if (str_starts_with($key, 'traefik.http.routers.') && str_ends_with($key, '.rule')) {
                return $value;
            }
        }

        return null;
    }
}
