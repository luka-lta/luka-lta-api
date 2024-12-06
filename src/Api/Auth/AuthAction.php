<?php

namespace LukaLtaApi\Api\Auth;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Auth\Service\AuthService;
use LukaLtaApi\Api\RequestValidator;
use LukaLtaApi\Value\Result\ApiResult;
use LukaLtaApi\Value\Result\JsonResult;
use LukaLtaApi\Value\User\UserEmail;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthAction extends ApiAction
{
    public function __construct(
        private readonly AuthService      $service,
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

        $jwt = $this->service->login(
            UserEmail::from($request->getParsedBody()['email']),
            $request->getParsedBody()['password']
        );

        return ApiResult::from(
            JsonResult::from(
                'User logged in',
                [
                    'token' => $jwt->getToken()
                ]
            )
        )->getResponse($response);
    }
}
