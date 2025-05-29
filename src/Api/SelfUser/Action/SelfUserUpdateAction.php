<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\SelfUser\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\RequestValidator;
use LukaLtaApi\Api\SelfUser\Service\SelfUserService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SelfUserUpdateAction extends ApiAction
{
    public function __construct(
        private readonly SelfUserService $service,
        private readonly RequestValidator $validator,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rules = [
            'userId' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'email' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'avatarUrl' => ['required' => false, 'location' => RequestValidator::LOCATION_BODY],
        ];
        
        $this->validator->validate($request, $rules);

        return $this->service->updateUser($request)->getResponse($response);
    }
}
