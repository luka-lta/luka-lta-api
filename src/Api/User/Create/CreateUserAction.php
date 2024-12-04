<?php

namespace LukaLtaApi\Api\User\Create;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\RequestValidator;
use LukaLtaApi\Api\User\Create\Service\CreateUserService;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\User\UserEmail;
use LukaLtaApi\Value\User\UserPassword;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CreateUserAction extends ApiAction
{
    public function __construct(
        private readonly CreateUserService $service,
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

        $this->service->createUser(
            UserEmail::from($request->getParsedBody()['email']),
            UserPassword::fromPlain($request->getParsedBody()['password'])
        );

        return ApiResult::from(JsonResult::from('User created'))->getResponse($response);
    }
}
