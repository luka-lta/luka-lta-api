<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Register\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Register\Service\RegisterService;
use LukaLtaApi\Api\RequestValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class RegisterUserAction extends ApiAction
{
    public function __construct(
        private readonly RegisterService $registerService,
        private readonly RequestValidator $validator,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rules = [
            'email' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'password' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
            'previewToken' => ['required' => true, 'location' => RequestValidator::LOCATION_BODY],
        ];

        $this->validator->validate($request, $rules);

        return $this->registerService->registerUser($request)->getResponse($response);
    }
}
