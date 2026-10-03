<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectService;
use LukaLtaApi\Value\Project\ProjectId;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl http://localhost/api/v1/projects/manage/<uuid> --header 'Authorization: <raw-jwt>'
//        --header 'Origin: <origin>'
class GetManagedProjectAction extends ApiAction
{
    public function __construct(
        private readonly ProjectService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $projectId = ProjectId::fromString((string) $request->getAttribute('projectId'));

        return $this->service->getProjectById($projectId)->getResponse($response);
    }
}
