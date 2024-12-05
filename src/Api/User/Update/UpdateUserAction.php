<?php

namespace LukaLtaApi\Api\User\Update;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\RequestValidator;
use LukaLtaApi\Api\User\Update\Service\UserUpdateService;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\User\UserEmail;
use LukaLtaApi\Value\User\UserId;
use LukaLtaApi\Value\User\UserPassword;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UpdateUserAction extends ApiAction
{
    public function __construct(
        private readonly UserUpdateService $service,
        private readonly RequestValidator $validator,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rules = [
            'email' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'password' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'avatarUrl' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
        ];

        $this->validator->validate($request, $rules);

        $this->service->update(
            UserId::fromString($request->getAttribute('userId')),
            UserEmail::from($request->getParsedBody()['email']),
            UserPassword::fromPlain($request->getParsedBody()['password']),
            $request->getParsedBody()['avatarUrl']
        );

        return ApiResult::from(JsonResult::from('User updated'))->getResponse($response);
    }
}
