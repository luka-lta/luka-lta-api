<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl http://localhost/api/v1/projects
class GetAllProjectsAction extends ApiAction
{
    public function __construct(
        private readonly ProjectService $service,
    ) {
    }

    /**
     * Oeffentliche Route: filtert immer hart auf sichtbare Projekte. Der
     * Auth-Status wird hier bewusst NICHT ausgewertet — das Dashboard nutzt
     * die geschuetzte /projects/manage-Route.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getAllProjects(true)->getResponse($response);
    }
}
