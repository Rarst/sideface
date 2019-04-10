<?php
declare(strict_types=1);

namespace Rarst\Sideface\Run;

use Psr\Http\Message\ResponseInterface;
use Rarst\Sideface\Responder\Responder;
use Slim\Http\Request;
use Slim\Http\Response;

class RunAction
{
    /** @var RunDomainLogic */
    private $domain;

    /** @var Responder */
    private $responder;

    public function __construct(RunDomainLogic $domain, Responder $responder)
    {
        $this->domain    = $domain;
        $this->responder = $responder;
    }

    public function list(Request $request, Response $response, array $args): ResponseInterface
    {
        $source = $args['source'] ?? null;

        return $this->responder->list($response, [
            'runs'   => $this->domain->getRuns($source),
            'source' => $source,
        ]);
    }

    public function show(Request $request, Response $response, array $args): ResponseInterface
    {
        $symbol = $args['symbol'] ?? null;

        return $this->responder->report($response, [
            'source' => $args['source'],
            'run'    => $args['run'],
            'symbol' => $symbol,
            'body'   => $this->domain->getRunReport($args['run'], $args['source'], $symbol),
        ]);
    }

    public function diff(Request $request, Response $response, array $args): ResponseInterface
    {
        $symbol = $args['symbol'] ?? null;

        return $this->responder->report($response, [
            'source' => $args['source'],
            'run'    => $args['run1'] . '–' . $args['run2'],
            'symbol' => $symbol,
            'body'   => $this->domain->getDiffReport($args['run1'], $args['run2'], $args['source'], $symbol),
        ]);
    }
}
