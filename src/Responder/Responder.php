<?php
declare(strict_types=1);

namespace Rarst\Sideface\Responder;

use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Views\Twig;

class Responder
{
    /** @var Twig */
    private $view;

    public function __construct(Twig $view)
    {
        $this->view = $view;
    }

    public function list(Response $response, array $payload): ResponseInterface
    {
        return $this->view->render($response, 'runs-list.twig', $payload);
    }

    public function report(Response $response, array $payload): ResponseInterface
    {
        return $this->view->render($response, 'report.twig', $payload);
    }

    public function callgraph(Response $response, array $payload): ResponseInterface
    {
        return $this->view->render($response, 'callgraph.twig', $payload);
    }

    public function image(Response $response, array $payload): ResponseInterface
    {
        $response = $response->write($payload['image']);
        $response = $response->withHeader('Content-Type', $this->getMimeType($payload['type']));

        return $response;
    }

    private function getMimeType(string $imageType): string
    {
        switch ($imageType) {
            case 'svg':
                $mime = 'image/svg+xml';
                break;
            case 'ps':
                $mime = 'application/postscript';
                break;
            case 'jpg':
                $mime = 'image/jpeg';
                break;
            default:
                $mime = "image/{$imageType}";
        }

        return $mime;
    }
}
