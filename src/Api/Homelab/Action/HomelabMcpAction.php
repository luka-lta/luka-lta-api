<?php

declare(strict_types=1);

namespace LukaLtaApi\Api\Homelab\Action;

use LukaLtaApi\Api\ApiAction;
use LukaLtaApi\Api\Homelab\Mcp\HomelabTopologyMcpServerFactory;
use Mcp\Server\Transport\Http\Middleware\PassthroughMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\StreamFactory;

// Usage: claude mcp add --transport http homelab https://<api-host>/api/v1/homelab/mcp \
//   --header 'X-API-Key: <api-key>' --header 'Origin: <key-origin>'
class HomelabMcpAction extends ApiAction
{
    public function __construct(
        private readonly HomelabTopologyMcpServerFactory $serverFactory,
    ) {
    }

    protected function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $server = $this->serverFactory->create('mcp:api-key-' . $request->getAttribute('apiKeyId'));

        // Die SDK-Standard-Middleware (CORS, DNS-Rebinding-Schutz) entfällt bewusst: Zugriff ist nur mit
        // API-Key-Header möglich, den ein Browser nicht ungefragt mitschickt — und CORS setzt die API selbst.
        $transport = new StreamableHttpTransport(
            $request,
            new ResponseFactory(),
            new StreamFactory(),
            middleware: [new PassthroughMiddleware()],
        );

        return $server->run($transport);
    }
}
