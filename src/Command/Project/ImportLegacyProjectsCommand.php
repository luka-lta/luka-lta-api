<?php

declare(strict_types=1);

namespace LukaLtaApi\Command\Project;

use LukaLtaApi\Api\Project\Service\ProjectAssetService;
use LukaLtaApi\Repository\ProjectRepository;
use LukaLtaApi\Value\Project\Asset\ProjectAssetType;
use LukaLtaApi\Value\Project\Project;
use LukaLtaApi\Value\Project\ProjectSlug;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Einmaliger Import der zuvor im Portfolio-Frontend hartkodierten Projekte.
 * Idempotent: bereits vorhandene Slugs werden uebersprungen, damit ein
 * zweiter Lauf keine Duplikate erzeugt.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
#[AsCommand(name: 'projects:import-legacy', description: 'Imports the formerly hardcoded portfolio projects')]
class ImportLegacyProjectsCommand extends Command
{
    /** Verzeichnis, in das die Bilder aus dem Portfolio-Repo kopiert wurden */
    private const string IMAGE_SOURCE_DIR = __DIR__ . '/../../../var/legacy-projects';

    /** Projektdaten 1:1 aus luka-lta/src/lib/projects-data.ts uebernommen */
    private const array LEGACY_PROJECTS = [
        [
            'slug'             => 'luka-lta-api',
            'name'             => 'luka-lta-api',
            'shortDescription' => 'API for User Management, LinkTracking and ApiKey Management',
            'description'      => 'A RESTful API built with PHP and the Slim Framework. Handles user '
                . 'authentication via JWT, link click tracking with analytics, and API key management. '
                . 'Backed by MySQL for persistent storage and Redis for caching.',
            'techStack'        => ['PHP', 'Slim Framework', 'MySQL', 'Redis', 'JWT', 'Docker'],
            'websiteUrl'       => 'https://luka-lta.dev',
            'liveLabel'        => null,
            'repositoryUrl'    => 'https://github.com/luka-lta/luka-lta-api',
            'repositoryOwner'  => 'luka-lta',
            'repositoryName'   => 'luka-lta-api',
            'role'             => 'Creator',
            'projectYear'      => 2022,
            'isClientProject'  => false,
            'screenshots'      => ['luka-lta-api.webp', 'frontend.png'],
        ],
        [
            'slug'             => 'mexcal',
            'name'             => 'Mexcal Hameln',
            'shortDescription' => 'Restaurant website for a local client in Hameln',
            'description'      => 'Restaurant website built for Mexcal Hameln, a local Mexican restaurant. '
                . 'Showcases the full menu, opening hours, location, and a contact section. Built as a '
                . 'client project with emphasis on mobile-first design, fast load times, and a warm '
                . 'visual identity that matches the brand.',
            'techStack'        => ['TypeScript', 'React', 'Vite', 'Tailwind CSS'],
            'websiteUrl'       => 'https://mexcal-hameln.de',
            'liveLabel'        => null,
            'repositoryUrl'    => null,
            'repositoryOwner'  => null,
            'repositoryName'   => null,
            'role'             => 'Creator',
            'projectYear'      => 2026,
            'isClientProject'  => true,
            'screenshots'      => ['mexcal-hameln.png', 'mexcal-website.png'],
        ],
        [
            'slug'             => 'luka-lta-backend',
            'name'             => 'luka-lta-backend',
            'shortDescription' => 'Backend dashboard to manage API resources',
            'description'      => 'A React-based admin dashboard for managing links, users, and API keys '
                . 'exposed by the luka-lta-api. Built with shadcn/ui components and Node.js tooling.',
            'techStack'        => ['React', 'Node.js', 'Shadcn/ui'],
            'websiteUrl'       => 'https://luka-lta.dev',
            'liveLabel'        => null,
            'repositoryUrl'    => 'https://github.com/luka-lta/luka-lta-backend',
            'repositoryOwner'  => 'luka-lta',
            'repositoryName'   => 'luka-lta-backend',
            'role'             => 'Creator',
            'projectYear'      => 2024,
            'isClientProject'  => false,
            'screenshots'      => ['backend.png'],
        ],
        [
            'slug'             => 'kindled',
            'name'             => 'Kindled',
            'shortDescription' => 'iOS habit tracker app to build and maintain daily habits',
            'description'      => 'Kindled is a native iOS habit tracking app built with Swift and SwiftUI. '
                . 'It helps users build and maintain daily habits through streak tracking, visual progress '
                . 'indicators, and smart reminders. Designed with a clean, minimal interface focused on '
                . 'reducing friction between intention and action.',
            'techStack'        => ['Swift', 'SwiftUI', 'iOS', 'Core Data'],
            'websiteUrl'       => 'https://apps.apple.com/us/app/kindled/id6765896395',
            'liveLabel'        => 'App Store',
            'repositoryUrl'    => 'https://github.com/luka-lta/kindled',
            'repositoryOwner'  => 'luka-lta',
            'repositoryName'   => 'kindled',
            'role'             => 'Creator',
            'projectYear'      => 2025,
            'isClientProject'  => false,
            'screenshots'      => ['kindled.png'],
        ],
        [
            'slug'             => 'dj-guide',
            'name'             => 'DJ Guide',
            'shortDescription' => 'Reference app for Pioneer DJ equipment — controls, Camelot wheel, '
                . 'troubleshooting & fundamentals',
            'description'      => 'DJ Guide is a mobile reference app for DJs using Pioneer equipment and '
                . 'Rekordbox. Covers controller controls & shortcuts, the Camelot key system for harmonic '
                . 'mixing, DJ fundamentals, and a troubleshooting guide. Built with Expo and React Native, '
                . 'available on the App Store.',
            'techStack'        => ['React Native', 'Expo', 'TypeScript', 'React 19'],
            'websiteUrl'       => 'https://apps.apple.com/us/app/dj-guide-pioneer-rekordbox/id6789975788',
            'liveLabel'        => 'App Store',
            'repositoryUrl'    => 'https://github.com/luka-lta/dj-guide',
            'repositoryOwner'  => 'luka-lta',
            'repositoryName'   => 'dj-guide',
            'role'             => 'Creator',
            'projectYear'      => 2025,
            'isClientProject'  => false,
            'screenshots'      => ['dj_guide.png'],
        ],
        [
            'slug'             => 'luka-lta-frontend',
            'name'             => 'luka-lta-frontend',
            'shortDescription' => 'Portfolio page about myself and link collection',
            'description'      => 'My personal portfolio website and link-in-bio page. Built with React, '
                . 'TypeScript, Tailwind CSS, and Framer Motion. Features a project showcase, skills '
                . 'section, timeline, contact form, and a Linktree-style links page.',
            'techStack'        => ['React', 'TypeScript', 'Tailwind CSS', 'Shadcn/ui', 'Framer Motion', 'Node.js'],
            'websiteUrl'       => 'https://luka-lta.dev',
            'liveLabel'        => null,
            'repositoryUrl'    => 'https://github.com/luka-lta/luka-lta-frontend',
            'repositoryOwner'  => 'luka-lta',
            'repositoryName'   => 'luka-lta-frontend',
            'role'             => 'Creator',
            'projectYear'      => 2025,
            'isClientProject'  => false,
            'screenshots'      => ['frontend.png'],
        ],
    ];

    public function __construct(
        private readonly ProjectRepository   $repository,
        private readonly ProjectAssetService $assetService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sortOrder = 0;

        foreach (self::LEGACY_PROJECTS as $data) {
            $slug = ProjectSlug::fromString($data['slug']);

            if ($this->repository->getBySlug($slug, false) !== null) {
                $output->writeln(sprintf('Skipped %s (already imported)', $data['slug']));
                $sortOrder++;
                continue;
            }

            $project = Project::create([
                ...$data,
                'status'    => 'active',
                'isVisible' => true,
                'sortOrder' => $sortOrder,
            ]);

            $this->repository->create($project);
            $this->importImages($project, $data['screenshots'], $output);

            $output->writeln(sprintf('Imported %s', $data['slug']));
            $sortOrder++;
        }

        return Command::SUCCESS;
    }

    /**
     * Das Altmodell kennt kein Logo und kein Cover — der erste Screenshot wird
     * daher zusaetzlich als logo und cover hochgeladen, damit die Darstellung
     * ab dem ersten Tag vollstaendig ist. Beide lassen sich spaeter im
     * Dashboard durch echte Assets ersetzen.
     */
    private function importImages(Project $project, array $screenshots, OutputInterface $output): void
    {
        foreach (array_values($screenshots) as $index => $fileName) {
            $uploadedFile = $this->asUploadedFile($fileName);

            if ($uploadedFile === null) {
                $output->writeln(sprintf('  WARNING: image %s not found, skipped', $fileName));
                continue;
            }

            $this->assetService->upload($project->getProjectId(), ProjectAssetType::SCREENSHOT, $uploadedFile, null);

            if ($index !== 0) {
                continue;
            }

            foreach ([ProjectAssetType::LOGO, ProjectAssetType::COVER] as $type) {
                $duplicate = $this->asUploadedFile($fileName);

                if ($duplicate === null) {
                    continue;
                }

                $this->assetService->upload($project->getProjectId(), $type, $duplicate, null);
            }
        }
    }

    private function asUploadedFile(string $fileName): ?UploadedFile
    {
        $path = self::IMAGE_SOURCE_DIR . '/' . basename($fileName);

        if (!is_file($path)) {
            return null;
        }

        $mimeType = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png'          => 'image/png',
            'webp'         => 'image/webp',
            'jpg', 'jpeg'  => 'image/jpeg',
            default        => null,
        };

        if ($mimeType === null) {
            return null;
        }

        return new UploadedFile(
            (new StreamFactory())->createStreamFromFile($path),
            basename($path),
            $mimeType,
            filesize($path) ?: null,
            UPLOAD_ERR_OK,
        );
    }
}
