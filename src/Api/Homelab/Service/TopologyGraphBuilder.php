<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Service;

use LukaLtaApi\Value\Alert\Alerts;
use LukaLtaApi\Value\Homelab\Container;
use LukaLtaApi\Value\Homelab\Containers;
use LukaLtaApi\Value\Homelab\Host;
use LukaLtaApi\Value\Homelab\Hosts;
use LukaLtaApi\Value\Homelab\NodeReference;
use LukaLtaApi\Value\Homelab\TopologyEdges;
use LukaLtaApi\Value\Homelab\TopologyNode;
use LukaLtaApi\Value\Homelab\TopologyNodes;

/**
 * Baut den Graphen ausschließlich aus kuratierten Knoten und Kanten. Es werden keine
 * Beziehungen aus Labels oder Netzen abgeleitet — das ist Aufgabe des Reviews (Proposals).
 * Live-Daten fließen nur über Knoten-Verweise ein: Ein Knoten, der auf einen Host oder
 * Service zeigt, übernimmt dessen aktuellen Status.
 */
class TopologyGraphBuilder
{
    /** Alert-Typ des Heartbeat-Checks, der einen Host als offline markiert */
    private const string HOST_OFFLINE_ALERT = 'host.offline';

    public function build(
        Hosts $hosts,
        Containers $containers,
        TopologyNodes $nodes,
        TopologyEdges $edges,
        Alerts $activeAlerts,
    ): array {
        $hostsById              = $this->indexHosts($hosts);
        $containersByService    = $this->indexContainersByService($containers);
        $hostAlertTypes         = $this->collectAlertTypes($activeAlerts, 'hostId');
        $containerIdsWithAlerts = $this->collectAlertTypes($activeAlerts, 'containerId');

        $graphNodes = [];
        foreach ($nodes as $node) {
            $graphNodes[] = $this->buildNode(
                $node,
                $hostsById,
                $containersByService,
                $hostAlertTypes,
                $containerIdsWithAlerts,
            );
        }

        return ['nodes' => $graphNodes, 'edges' => iterator_to_array($edges)];
    }

    private function buildNode(
        TopologyNode $node,
        array $hostsById,
        array $containersByService,
        array $hostAlertTypes,
        array $containerIdsWithAlerts,
    ): array {
        $reference = $node->getReference();
        $graphNode = $node->jsonSerialize();

        if ($reference->isNone()) {
            return $graphNode;
        }

        $hostId = $reference->getHostId()->asString();
        $host   = $hostsById[$hostId] ?? null;

        if ($reference->getType() === NodeReference::TYPE_HOST) {
            $graphNode['status'] = $host !== null ? $this->hostStatus($hostAlertTypes[$hostId] ?? []) : 'unknown';
            $graphNode['ref']    = [
                ...$reference->jsonSerialize(),
                'resolved' => $host !== null,
                'hostName' => $host?->getName(),
            ];

            return $graphNode;
        }

        $container = $containersByService[$hostId][$reference->getServiceKey()->asString()] ?? null;

        $graphNode['status'] = $container !== null
            ? $this->containerStatus($container, isset($containerIdsWithAlerts[$container->getContainerId()]))
            : 'unknown';
        $graphNode['ref'] = [
            ...$reference->jsonSerialize(),
            'resolved'    => $container !== null,
            'hostName'    => $host?->getName(),
            'containerId' => $container?->getContainerId(),
        ];

        return $graphNode;
    }

    /** @return array<string, Host> */
    private function indexHosts(Hosts $hosts): array
    {
        $index = [];
        foreach ($hosts as $host) {
            $index[$host->getHostId()] = $host;
        }

        return $index;
    }

    /**
     * Bei mehreren Replicas eines Services gewinnt ein laufender Container, damit ein
     * einzelner gestoppter Rest den Knoten nicht als offline markiert.
     *
     * @return array<string, array<string, Container>>
     */
    private function indexContainersByService(Containers $containers): array
    {
        $index = [];
        foreach ($containers as $container) {
            $hostId     = $container->getHostId();
            $serviceKey = $container->getServiceKey()->asString();
            $existing   = $index[$hostId][$serviceKey] ?? null;

            if ($existing !== null && $existing->getStatus() === 'running') {
                continue;
            }

            $index[$hostId][$serviceKey] = $container;
        }

        return $index;
    }

    /** @return array<string, array<string, true>> Kontext-ID => Alert-Typen */
    private function collectAlertTypes(Alerts $alerts, string $contextKey): array
    {
        $types = [];
        foreach ($alerts as $alert) {
            $value = $alert->getContext()[$contextKey] ?? null;
            if ($value !== null) {
                $types[$value][$alert->getType()] = true;
            }
        }

        return $types;
    }

    private function hostStatus(array $alertTypes): string
    {
        if (isset($alertTypes[self::HOST_OFFLINE_ALERT])) {
            return 'offline';
        }

        return empty($alertTypes) ? 'healthy' : 'warning';
    }

    private function containerStatus(Container $container, bool $hasAlert): string
    {
        $status = match ($container->getStatus()) {
            'running' => 'healthy',
            'warning' => 'warning',
            'unhealthy', 'stopped' => 'offline',
            default => 'unknown',
        };

        // Ein Alert eskaliert nur "healthy" zu "warning", ein schlechterer Status bleibt.
        return $hasAlert && $status === 'healthy' ? 'warning' : $status;
    }
}
