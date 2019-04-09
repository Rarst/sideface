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
}
