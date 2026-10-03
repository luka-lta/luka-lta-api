<?php

declare(strict_types=1);

namespace LukaLtaApi\Value\Project;

use DateTimeImmutable;
use LukaLtaApi\Exception\ApiInvalidArgumentException;
use LukaLtaApi\Value\Project\Asset\ProjectAssets;
use LukaLtaApi\Value\Project\Asset\ProjectAssetType;
use LukaLtaApi\Value\Project\Tag\ProjectTags;

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Project
{
    /** Maximale Anzahl Eintraege in tech_stack */
    private const int MAX_TECH_STACK_ITEMS = 20;

    /** Maximale Laenge eines einzelnen tech_stack-Eintrags */
    private const int MAX_TECH_STACK_ITEM_LENGTH = 50;

    /** Nullable URL-Felder, die beim Schreiben identisch validiert werden */
    private const array URL_FIELDS = [
        'websiteUrl'       => 'websiteUrl',
        'repositoryUrl'    => 'repositoryUrl',
        'demoUrl'          => 'demoUrl',
        'documentationUrl' => 'documentationUrl',
    ];

    /** Erlaubte Schemes fuer URL-Felder — verhindert u. a. javascript: und mailto: */
    private const array ALLOWED_URL_SCHEMES = ['http', 'https'];

    private ProjectTags $tags;

    private ProjectAssets $assets;

    private function __construct(
        private readonly ProjectId     $projectId,
        private ProjectName            $name,
        private ProjectSlug            $slug,
        private ?string                $shortDescription,
        private ?string                $description,
        private ProjectStatus          $status,
        private bool                   $isVisible,
        private ?string                $category,
        private array                  $techStack,
        private ?string                $websiteUrl,
        private ?string                $liveLabel,
        private ?string                $repositoryUrl,
        private ?string                $repositoryOwner,
        private ?string                $repositoryName,
        private ?string                $demoUrl,
        private ?string                $documentationUrl,
        private ?string                $role,
        private ?int                   $projectYear,
        private bool                   $isClientProject,
        private ?array                 $metadata,
        private int                    $sortOrder,
        private readonly ?DateTimeImmutable $createdAt,
        private ?DateTimeImmutable     $updatedAt,
    ) {
        $this->tags   = ProjectTags::empty();
        $this->assets = ProjectAssets::empty();
    }

    public static function create(array $data): self
    {
        $name = ProjectName::fromString((string) $data['name']);
        $slug = isset($data['slug']) && trim((string) $data['slug']) !== ''
            ? ProjectSlug::fromString((string) $data['slug'])
            : ProjectSlug::fromName((string) $data['name']);

        $project = new self(
            ProjectId::generate(),
            $name,
            $slug,
            null,
            null,
            ProjectStatus::DEVELOPMENT,
            true,
            null,
            [],
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            false,
            null,
            0,
            new DateTimeImmutable(),
            null,
        );

        $project->applyChanges($data);

        return $project;
    }

    public static function fromDatabase(array $row): self
    {
        return new self(
            ProjectId::fromString($row['project_id']),
            ProjectName::fromString($row['name']),
            ProjectSlug::fromString($row['slug']),
            $row['short_description'],
            $row['description'],
            ProjectStatus::fromString($row['status']),
            (bool) $row['is_visible'],
            $row['category'],
            $row['tech_stack'] !== null ? (array) json_decode($row['tech_stack'], true, 512, JSON_THROW_ON_ERROR) : [],
            $row['website_url'],
            $row['live_label'],
            $row['repository_url'],
            $row['repository_owner'],
            $row['repository_name'],
            $row['demo_url'],
            $row['documentation_url'],
            $row['role'],
            $row['project_year'] !== null ? (int) $row['project_year'] : null,
            (bool) $row['is_client_project'],
            $row['metadata'] !== null ? (array) json_decode($row['metadata'], true, 512, JSON_THROW_ON_ERROR) : null,
            (int) $row['sort_order'],
            new DateTimeImmutable($row['created_at']),
            $row['updated_at'] !== null ? new DateTimeImmutable($row['updated_at']) : null,
        );
    }

    /** Partielles Update: nur uebergebene Keys werden angewendet (PATCH-Semantik). */
    public function applyChanges(array $data): void
    {
        if (isset($data['name'])) {
            $this->name = ProjectName::fromString((string) $data['name']);
        }

        if (isset($data['slug'])) {
            $this->slug = ProjectSlug::fromString((string) $data['slug']);
        }

        if (array_key_exists('shortDescription', $data)) {
            $this->shortDescription = $this->asNullableString($data['shortDescription']);
        }

        if (array_key_exists('description', $data)) {
            $this->description = $this->asNullableString($data['description']);
        }

        if (isset($data['status'])) {
            $this->status = ProjectStatus::fromString((string) $data['status']);
        }

        if (isset($data['isVisible'])) {
            $this->isVisible = (bool) $data['isVisible'];
        }

        if (array_key_exists('category', $data)) {
            $this->category = $this->asNullableString($data['category']);
        }

        if (array_key_exists('techStack', $data)) {
            $this->techStack = $this->parseTechStack($data['techStack']);
        }

        if (array_key_exists('liveLabel', $data)) {
            $this->liveLabel = $this->asNullableString($data['liveLabel']);
        }

        if (array_key_exists('repositoryOwner', $data)) {
            $this->repositoryOwner = $this->asNullableString($data['repositoryOwner']);
        }

        if (array_key_exists('repositoryName', $data)) {
            $this->repositoryName = $this->asNullableString($data['repositoryName']);
        }

        if (array_key_exists('role', $data)) {
            $this->role = $this->asNullableString($data['role']);
        }

        if (array_key_exists('projectYear', $data)) {
            $this->projectYear = $data['projectYear'] !== null ? (int) $data['projectYear'] : null;
        }

        if (isset($data['isClientProject'])) {
            $this->isClientProject = (bool) $data['isClientProject'];
        }

        if (array_key_exists('metadata', $data)) {
            $this->metadata = $data['metadata'] !== null ? (array) $data['metadata'] : null;
        }

        if (isset($data['sortOrder'])) {
            $this->sortOrder = (int) $data['sortOrder'];
        }

        $this->applyUrlChanges($data);
        $this->updatedAt = new DateTimeImmutable();
    }

    private function applyUrlChanges(array $data): void
    {
        foreach (self::URL_FIELDS as $key => $property) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $this->{$property} = $this->parseUrl($data[$key], $key);
        }
    }

    private function parseUrl(mixed $value, string $fieldName): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $url = trim((string) $value);

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new ApiInvalidArgumentException(sprintf('Field %s must be a valid URL.', $fieldName), 400);
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if (!in_array($scheme, self::ALLOWED_URL_SCHEMES, true)) {
            throw new ApiInvalidArgumentException(
                sprintf('Field %s must use http or https.', $fieldName),
                400,
            );
        }

        return $url;
    }

    private function parseTechStack(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        if (!is_array($value)) {
            throw new ApiInvalidArgumentException('Field techStack must be an array of strings.', 400);
        }

        if (count($value) > self::MAX_TECH_STACK_ITEMS) {
            throw new ApiInvalidArgumentException('Field techStack must not exceed 20 entries.', 400);
        }

        $entries = [];
        foreach ($value as $entry) {
            if (!is_string($entry) || trim($entry) === '') {
                throw new ApiInvalidArgumentException('Field techStack must be an array of strings.', 400);
            }

            if (mb_strlen($entry) > self::MAX_TECH_STACK_ITEM_LENGTH) {
                throw new ApiInvalidArgumentException('Each techStack entry must not exceed 50 characters.', 400);
            }

            $entries[] = trim($entry);
        }

        return $entries;
    }

    private function asNullableString(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return trim((string) $value);
    }

    public function getProjectId(): ProjectId
    {
        return $this->projectId;
    }

    public function getSlug(): ProjectSlug
    {
        return $this->slug;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setTags(ProjectTags $tags): void
    {
        $this->tags = $tags;
    }

    public function setAssets(ProjectAssets $assets): void
    {
        $this->assets = $assets;
    }

    public function getAssets(): ProjectAssets
    {
        return $this->assets;
    }

    public function toDatabaseRow(): array
    {
        return [
            'project_id'        => $this->projectId->asString(),
            'name'              => (string) $this->name,
            'slug'              => $this->slug->asString(),
            'short_description' => $this->shortDescription,
            'description'       => $this->description,
            'status'            => $this->status->value,
            'is_visible'        => $this->isVisible ? 1 : 0,
            'category'          => $this->category,
            'tech_stack'        => json_encode($this->techStack, JSON_THROW_ON_ERROR),
            'website_url'       => $this->websiteUrl,
            'live_label'        => $this->liveLabel,
            'repository_url'    => $this->repositoryUrl,
            'repository_owner'  => $this->repositoryOwner,
            'repository_name'   => $this->repositoryName,
            'demo_url'          => $this->demoUrl,
            'documentation_url' => $this->documentationUrl,
            'role'              => $this->role,
            'project_year'      => $this->projectYear,
            'is_client_project' => $this->isClientProject ? 1 : 0,
            'metadata'          => $this->metadata !== null
                ? json_encode($this->metadata, JSON_THROW_ON_ERROR)
                : null,
            'sort_order'        => $this->sortOrder,
        ];
    }

    public function toArray(string $assetBaseUrl): array
    {
        $logo  = $this->assets->ofType(ProjectAssetType::LOGO);
        $cover = $this->assets->ofType(ProjectAssetType::COVER);

        return [
            'id'               => $this->projectId->asString(),
            'name'             => (string) $this->name,
            'slug'             => $this->slug->asString(),
            'shortDescription' => $this->shortDescription,
            'description'      => $this->description,
            'status'           => $this->status->value,
            'isVisible'        => $this->isVisible,
            'category'         => $this->category,
            'tags'             => $this->tags->toArray(),
            'techStack'        => $this->techStack,
            'websiteUrl'       => $this->websiteUrl,
            'liveLabel'        => $this->liveLabel,
            'repositoryUrl'    => $this->repositoryUrl,
            'repositoryOwner'  => $this->repositoryOwner,
            'repositoryName'   => $this->repositoryName,
            'demoUrl'          => $this->demoUrl,
            'documentationUrl' => $this->documentationUrl,
            'role'             => $this->role,
            'year'             => $this->projectYear,
            'isClientProject'  => $this->isClientProject,
            'sortOrder'        => $this->sortOrder,
            'logo'             => $logo?->toArray($assetBaseUrl),
            'cover'            => $cover?->toArray($assetBaseUrl),
            'screenshots'      => array_map(
                static fn($asset) => $asset->toArray($assetBaseUrl),
                $this->assets->allOfType(ProjectAssetType::SCREENSHOT),
            ),
            'createdAt'        => $this->createdAt?->format('Y-m-d H:i:s'),
            'updatedAt'        => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
