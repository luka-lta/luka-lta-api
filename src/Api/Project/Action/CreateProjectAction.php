<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Project\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Project\Service\ProjectService;
use LukaLtaApi\Api\RequestValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Usage: curl -X POST http://localhost/api/v1/projects --header 'Authorization: <raw-jwt>' --header 'Origin: <origin>'
//        --header 'Content-Type: application/json' --data '{"name":"My New App"}'
class CreateProjectAction extends ApiAction
{
    public function __construct(
        private readonly RequestValidator $requestValidator,
        private readonly ProjectService   $service,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->requestValidator->validate($request, [
            'name' => ['required' => true, 'location' => 'body'],
        ]);

        return $this->service->createProject((array) $request->getParsedBody())->getResponse($response);
    }
}
