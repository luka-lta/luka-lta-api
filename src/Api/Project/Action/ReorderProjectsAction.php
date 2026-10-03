<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectService;
use LukaLtaApi\Api\RequestValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X PATCH http://localhost/api/v1/projects/order --header 'Authorization: <raw-jwt>'
//        --header 'Origin: <origin>' --header 'Content-Type: application/json'
//        --data '{"projects":[{"projectId":"<uuid>","sortOrder":0}]}'
class ReorderProjectsAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator $requestValidator,
        private readonly ProjectService   $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->requestValidator->validate($request, [
            'projects' => ['required' => true, 'location' => 'body'],
        ]);

        $items = (array) $request->getParsedBody()['projects'];

        return $this->service->reorderProjects($items)->getResponse($response);
    }
}
