<?php
declare(strict_types=1);

namespace Rarst\Sideface\Callgraph;

use Psr\Http\Message\ResponseInterface;
use Rarst\Sideface\Responder\Responder;
use Slim\Http\Request;
use Slim\Http\Response;

class CallgraphAction
{
    /** @var CallgraphDomainLogic */
    private $domain;

    /** @var Responder */
    private $responder;

    public function __construct(CallgraphDomainLogic $domain, Responder $responder)
    {
        $this->domain    = $domain;
        $this->responder = $responder;

        ini_set('max_execution_time', '100');
    }

    public function show(Request $request, Response $response, array $args): ResponseInterface
    {
        $imageType = ltrim($args['callgraphType'] ?? 'svg', '.');
        $image     = $this->domain->getImage($args['run'], $args['source'], $imageType);

        // TODO image file responses are broken
//        if (! empty($args['callgraphType'])) {
        // echo $image;
//        }

        return $this->responder->callgraph($response, [
            'source' => $args['source'],
            'run'    => $args['run'],
            'svg'    => $image
        ]);
    }

    public function diff(Request $request, Response $response, array $args): ResponseInterface
    {
        $imageType = ltrim($args['callgraphType'] ?? 'svg', '.');
        $image     = $this->domain->getDiffImage($args['run1'], $args['run2'], $args['source'], $imageType);

        // TODO image file responses are broken
//        if (! empty($args['callgraphType'])) {
        // echo $image;
//        }

        return $this->responder->callgraph($response, [
            'source' => $args['source'],
            'run'    => $args['run1'] . '–' . $args['run2'],
            'svg'    => $image,
        ]);
    }
}
