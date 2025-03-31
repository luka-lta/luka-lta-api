<?php

namespace LukaLtaApi\Api\User\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\RequestValidator;
use LukaLtaApi\Api\User\Service\UserService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class UpdateUserAction extends ApiAction
{
    public function __construct(
        private readonly UserService $service,
        private readonly RequestValidator $validator,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rules = [
            'username' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'email' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'avatarUrl' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
            'isActive' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
        ];

        $this->validator->validate($request, $rules);

        return $this->service->updateUser($request)->getResponse($response);
    }
}
