<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl http://localhost/api/v1/projects/manage --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
class GetManagedProjectsAction extends ApiAction
{
    public function __construct(
        private readonly ProjectService $service,
    ) {
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getAllProjects(false)->getResponse($response);
    }
}
