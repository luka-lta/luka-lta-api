<?php

namespace LukaLtaApi\Api\User\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\RequestValidator;
use LukaLtaApi\Api\User\Service\UserService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CreateUserAction extends ApiAction
{
    public function __construct(
        private readonly UserService $service,
        private readonly RequestValidator $validator,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rules = [
            'email' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'password' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
        ];

        $this->validator->validate($request, $rules);

        return $this->service->createUser($request->getParsedBody())->getResponse($response);
    }
}
