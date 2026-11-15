<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Mcp;

use LukaLtaApi\Api\Homelab\Service\TopologyProposalService;
use LukaLtaApi\Api\Homelab\Service\TopologyService;
use LukaLtaApi\Exception\ApiException;
use LukaLtaApi\Repository\HomelabContainerRepository;
use LukaLtaApi\Repository\HomelabHostRepository;
use LukaLtaApi\Value\Homelab\Container;
use LukaLtaApi\Value\Homelab\NodeReference;
use LukaLtaApi\Value\Homelab\TopologyEdge;
use LukaLtaApi\Value\Homelab\TopologyNode;
use LukaLtaApi\Value\Homelab\TopologyProposalItem;
use Mcp\Server\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use Mcp\Server\Server;

/**
 * MCP-Server, über den Claude das Homelab liest und Topologie-Änderungen als Draft einreicht.
 * Bewusst ohne Schreibzugriff auf die Topologie selbst: Übernehmen passiert nur im Dashboard.
 * Env-Werte verlassen die API nie, nur deren Namen; Labels mit geheimnisverdächtigem Namen
 * werden geschwärzt.
 */
class HomelabTopologyMcpServerFactory
{
    private const string SERVER_NAME = 'luka-lta-homelab';

    private const string SERVER_VERSION = '1.0.0';

    /** Label-Namen, deren Wert ein Geheimnis sein kann (z. B. basicauth.users) */
    private const string SECRET_LABEL_PATTERN = '/(password|passwd|secret|token|key|credential|private|users|auth)/i';

    private const string REDACTED = '********';

    private const string INSTRUCTIONS = <<<TEXT
        Homelab topology curator. The topology map shows ONLY curated nodes and edges — nothing is derived
        automatically. Your job: inspect the inventory (list_hosts, list_services) and the current map
        (get_topology), then submit ONE proposal (submit_proposal) that the owner reviews and applies in the
        dashboard. Submitting replaces any open draft, so always include everything you want reviewed.

        Nodes: free-form lowercase "type" (e.g. internet, cloudflare, domain, router, server, reverse-proxy,
        database, cache, application, monitoring, storage). A node may reference live data via
        "ref": {"type":"host","hostId":…} or {"type":"service","hostId":…,"serviceKey":…} and then shows
        the live status; each host/service can be referenced by at most one node. Nodes without ref
        (internet, domains, external services) carry a manual status.
        Edges: relation is one of routes_to, depends_on, connects_to, hosted_on, exposes.
        Typical chain: internet -exposes-> domain -routes_to-> reverse-proxy -routes_to-> app
        -depends_on-> database; services -hosted_on-> their host node.

        Only propose what evidence supports (labels such as traefik.http.routers.*.rule, compose
        depends_on, shared networks, env variable NAMES like DB_HOST, or files the user showed you).
        Every item needs a short reasoning and evidence strings. Never put secrets into names,
        metadata or evidence.
        TEXT;

    public function __construct(
        private readonly HomelabHostRepository      $hostRepository,
        private readonly HomelabContainerRepository $containerRepository,
        private readonly TopologyService            $topologyService,
        private readonly TopologyProposalService    $proposalService,
    ) {
    }

    public function create(string $createdBy): Server
    {
        $readOnly = new ToolAnnotations(readOnlyHint: true, openWorldHint: false);

        return Server::builder()
            ->setServerInfo(self::SERVER_NAME, self::SERVER_VERSION)
            ->setInstructions(self::INSTRUCTIONS)
            ->addTool(
                fn (): array => $this->listHosts(),
                'list_hosts',
                'List hosts',
                'Lists all hosts reported by the homelab agent.',
                $readOnly,
                ['type' => 'object', 'properties' => new \stdClass()],
            )
            ->addTool(
                fn (?string $hostId = null): array => $this->listServices($hostId),
                'list_services',
                'List services',
                'Lists containers per host with their stable serviceKey (use it in node refs), image, status, '
                . 'networks, ports, labels (secret-looking values redacted) and environment variable names '
                . '(values are never exposed).',
                $readOnly,
                [
                    'type'       => 'object',
                    'properties' => [
                        'hostId' => ['type' => 'string', 'description' => 'Only list services of this host'],
                    ],
                ],
            )
            ->addTool(
                fn (): array => $this->topologyService->loadGraph(),
                'get_topology',
                'Get topology',
                'Returns the curated topology: nodes (with ref and live status) and edges (node ids).',
                $readOnly,
                ['type' => 'object', 'properties' => new \stdClass()],
            )
            ->addTool(
                fn (): array => ['proposal' => $this->proposalService->loadOpenDraft()?->jsonSerialize()],
                'get_open_proposal',
                'Get open proposal',
                'Returns the open draft proposal awaiting review, or null.',
                $readOnly,
                ['type' => 'object', 'properties' => new \stdClass()],
            )
            ->addTool(
                fn (string $title, array $items, ?string $summary = null): array => $this->submitProposal(
                    $createdBy,
                    $title,
                    $summary,
                    $items,
                ),
                'submit_proposal',
                'Submit topology proposal',
                'Submits a draft of topology changes for human review. Replaces any open draft. Items are '
                . 'applied in order; edges can point to nodes added in the same proposal via "new:<key>".',
                new ToolAnnotations(readOnlyHint: false, destructiveHint: false, openWorldHint: false),
                $this->submitProposalSchema(),
            )
            ->build();
    }

    private function listHosts(): array
    {
        $hosts = [];
        foreach ($this->hostRepository->loadAll() as $host) {
            $hosts[] = [
                'hostId'   => $host->getHostId(),
                'name'     => $host->getName(),
                'lastSeen' => $host->jsonSerialize()['updatedAt'],
            ];
        }

        return ['hosts' => $hosts];
    }

    private function listServices(?string $hostId): array
    {
        $services = [];
        foreach ($this->containerRepository->loadAll() as $container) {
            if ($hostId !== null && $container->getHostId() !== $hostId) {
                continue;
            }

            $services[] = $this->describeService($container);
        }

        return ['services' => $services];
    }

    private function describeService(Container $container): array
    {
        $labels = [];
        foreach ($container->getLabels() as $key => $value) {
            $labels[$key] = preg_match(self::SECRET_LABEL_PATTERN, (string) $key) === 1 ? self::REDACTED : $value;
        }

        return [
            'hostId'           => $container->getHostId(),
            'serviceKey'       => $container->getServiceKey()->asString(),
            'containerName'    => $container->getName(),
            'image'            => $container->getImage(),
            'status'           => $container->getStatus(),
            'networks'         => $container->getNetworks(),
            'ports'            => $container->getPorts(),
            'labels'           => (object) $labels,
            'environmentNames' => array_keys($container->getEnvironment()),
        ];
    }

    private function submitProposal(string $createdBy, string $title, ?string $summary, array $items): array
    {
        try {
            $proposal = $this->proposalService->submitProposal($title, $summary, $createdBy, $items);
        } catch (ApiException $exception) {
            // Fachliche Fehler gehen an das Modell zurück, damit es den Vorschlag korrigieren kann.
            throw new ToolCallException($exception->getMessage(), previous: $exception);
        }

        return [
            'proposalId' => $proposal->getProposalId(),
            'itemCount'  => count($proposal->getItems()),
            'status'     => $proposal->getStatus(),
            'message'    => 'Draft submitted. The owner reviews and applies it in the dashboard.',
        ];
    }

    private function submitProposalSchema(): array
    {
        $reference = [
            'type'        => ['object', 'null'],
            'description' => 'Live data this node represents',
            'properties'  => [
                'type'       => ['type' => 'string', 'enum' => [NodeReference::TYPE_HOST, NodeReference::TYPE_SERVICE]],
                'hostId'     => ['type' => 'string'],
                'serviceKey' => ['type' => 'string', 'description' => 'Required for type "service"'],
            ],
            'required'    => ['type', 'hostId'],
        ];

        return [
            'type'       => 'object',
            'properties' => [
                'title'   => ['type' => 'string', 'description' => 'Short title of the proposal'],
                'summary' => ['type' => 'string', 'description' => 'What changes and why, for the reviewer'],
                'items'   => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'properties' => [
                            'operation' => ['type' => 'string', 'enum' => TopologyProposalItem::OPERATIONS],
                            'payload'   => [
                                'type'        => 'object',
                                'description' => 'add_node: {key, type, name, status?, ref?, metadata?}; '
                                    . 'update_node: {nodeId, type?, name?, status?, metadata?}; '
                                    . 'remove_node: {nodeId}; '
                                    . 'add_edge: {source, target, relation, metadata?} where source/target is an '
                                    . 'existing node id or "new:<key>"; remove_edge: {edgeId}. '
                                    . 'status: ' . implode('|', TopologyNode::VALID_STATUSES) . '; relation: '
                                    . implode('|', TopologyEdge::VALID_RELATIONS) . '; ref: ' . json_encode($reference),
                            ],
                            'reasoning' => ['type' => 'string', 'description' => 'Why this change is correct'],
                            'evidence'  => [
                                'type'        => 'array',
                                'items'       => ['type' => 'string'],
                                'description' => 'Concrete facts, e.g. a label or file path',
                            ],
                        ],
                        'required'   => ['operation', 'payload', 'reasoning'],
                    ],
                ],
            ],
            'required'   => ['title', 'items'],
        ];
    }
}
