<?php
declare(strict_types=1);

namespace Rarst\Sideface\Callgraph;

use Psr\Http\Message\ResponseInterface;
use Rarst\Sideface\Callgraph;
use Rarst\Sideface\Responder\Responder;
use Rarst\Sideface\RunsHandler;
use Slim\Http\Request;
use Slim\Http\Response;

class CallgraphAction
{
    /** @var RunsHandler */
    private $handler;

    /** @var Responder */
    private $responder;

    public function __construct(RunsHandler $handler, Responder $responder)
    {
        $this->handler   = $handler;
        $this->responder = $responder;
    }

    public function show(Request $request, Response $response, array $args): ResponseInterface
    {
        ini_set('max_execution_time', '100');

        $run = $this->handler->getRun($args['run'], $args['source']);

        $callgraphType = $args['callgraphType'] ?? false;
        $callgraph     = new Callgraph([
            'type' => $callgraphType ? ltrim($callgraphType, '.') : 'svg',
        ]);

        if ($callgraphType) {
            $callgraph->render_image($run);
            return ''; // TODO wrapper, headers
        }
        ob_start();
        $callgraph->render_image($run);
        $svg = ob_get_clean();

        return $this->responder->callgraph($response, [
            'source' => $args['source'],
            'run'    => $run->getId(),
            'svg'    => $svg
        ]);
    }

    public function diff(Request $request, Response $response, array $args): ResponseInterface
    {
        ini_set('max_execution_time', '100');

        $source        = $args['source'];
        $run1          = $this->handler->getRun($args['run1'], $source);
        $run2          = $this->handler->getRun($args['run2'], $source);
        $callgraphType = $args['callgraphType'] ?? false;
        $callgraph     = new Callgraph([
            'type' => $callgraphType ? ltrim($callgraphType, '.') : 'svg',
        ]);

        if ($callgraphType) {
            $callgraph->render_diff_image($run1, $run2);
            return ''; // TODO wrapper, headers
        }
        ob_start();
        $callgraph->render_diff_image($run1, $run2);
        $svg = ob_get_clean();

        return $this->responder->callgraph($response, [
            'source' => $source,
            'run'    => $run1->getId() . '-' . $run2->getId(),
            'svg'    => $svg,
        ]);
    }
}
