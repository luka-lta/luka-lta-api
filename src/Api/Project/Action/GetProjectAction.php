<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectService;
use LukaLtaApi\Value\Project\ProjectSlug;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl http://localhost/api/v1/projects/luka-lta-api
class GetProjectAction extends ApiAction
{
    public function __construct(
        private readonly ProjectService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $slug = ProjectSlug::fromString((string) $request->getAttribute('slug'));

        return $this->service->getProjectBySlug($slug)->getResponse($response);
    }
}
