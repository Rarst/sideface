<?php
declare(strict_types=1);

namespace Rarst\Sideface\Run;

use Psr\Http\Message\ResponseInterface;
use Rarst\Sideface\Report;
use Rarst\Sideface\Responder\Responder;
use Rarst\Sideface\RunInterface;
use Rarst\Sideface\RunsHandler;
use Slim\Http\Request;
use Slim\Http\Response;

class RunAction
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

    public function list(Request $request, Response $response, array $args): ResponseInterface
    {
        $runsList = $this->handler->getRunsList();
        $source   = $args['source'] ?? false;

        if ($source) {
            $runsList = array_filter($runsList, static function ($run) use ($source) {
                /** @var RunInterface $run */
                return $run->getSource() === $source;
            });
        }

        return $this->responder->list($response, ['runs' => $runsList, 'source' => $source]);
    }

    public function show(Request $request, Response $response, array $args): ResponseInterface
    {
        //    global $wts;
        // TODO aggregate runs stuff
        // run may be a single run or a comma separate list of runs
        // that'll be aggregated. If "wts" (a comma separated list
        // of integral weights is specified), the runs will be
        // aggregated in that ratio.
        //
        //    $runs_array = explode(',', $runId);
        //    if (count($runs_array) == 1) {
        //        $runData = $run->getData();
        //    } else {
        //        if (! empty( $wts )) {
        //            $wts_array = explode(",", $wts);
        //        } else {
        //            $wts_array = null;
        //        }
        //        $data    = $report->aggregate_runs($runsHandler, $runs_array, $wts_array, $source, false);
        //        $runData = $data['raw'];
        //    }

        $run    = $this->handler->getRun($args['run'], $args['source']);
        $report = new Report(['source' => $args['source'], 'run' => $run->getId()]);
        $symbol = $args['symbol'] ?? null;
        $report->profilerReport($run, $symbol);

        return $this->responder->report($response, [
            'source' => $args['source'],
            'run'    => $run->getId(),
            'symbol' => $symbol,
            'body'   => $report->getBody(),
        ]);
    }

    public function diff(Request $request, Response $response, array $args): ResponseInterface
    {
        $source = $args['source'];
        $run1   = $this->handler->getRun($args['run1'], $source);
        $run2   = $this->handler->getRun($args['run2'], $source);
        $run    = $run1->getId() . '-' . $run2->getId();
        $symbol = $args['symbol'] ?? null;
        $report = new Report(['source' => $source, 'run' => $run]);
        $report->profilerDiffReport($run1, $run2, $symbol);

        return $this->responder->report($response, [
            'source' => $source,
            'run'    => $run,
            'symbol' => $symbol,
            'body'   => $report->getBody(),
        ]);
    }
}
