<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectTagService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl http://localhost/api/v1/projects/tags --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
class GetProjectTagsAction extends ApiAction
{
    public function __construct(
        private readonly ProjectTagService $service,
    ) {
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->service->getTags()->getResponse($response);
    }
}
