<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectTagService;
use LukaLtaApi\Api\RequestValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X POST http://localhost/api/v1/projects/tags --header 'Authorization: <raw-jwt>'
//        --header 'Origin: <origin>' --header 'Content-Type: application/json' --data '{"name":"Analytics"}'
class CreateProjectTagAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator  $requestValidator,
        private readonly ProjectTagService $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->requestValidator->validate($request, [
            'name' => ['required' => true, 'location' => 'body'],
        ]);

        $name = (string) $request->getParsedBody()['name'];

        return $this->service->createTag($name)->getResponse($response);
    }
}
