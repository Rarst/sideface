<?php

namespace Rarst\Sideface;

use Slim\App;
use Slim\Http\Uri;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;

require __DIR__ . '/../vendor/autoload.php';

$app = new App([
    'settings'     => [
        'displayErrorDetails' => true,
    ],
    'handler.runs' => static function () {
        return new RunsHandler();
    },
    'view'         => static function ($container) {
        $view   = new Twig(__DIR__ . '/../src/twig');
        $router = $container['router'];
        $uri    = Uri::createFromEnvironment($container['environment']);
        $view->addExtension(new TwigExtension($router, $uri));

        return $view;
    }
]);

$app->get('/[{source}]', function ($request, $response, $args) {

    $runsHandler = $this['handler.runs'];
    $runsList    = $runsHandler->getRunsList();
    $source      = $args['source'] ?? false;

    if ($source) {
        $runsList = array_filter($runsList, static function ($run) use ($source) {
            /** @var RunInterface $run */
            return $run->getSource() === $source;
        });
    }

    return $this->view->render($response, 'runs-list.twig', ['runs' => $runsList, 'source' => $source]);
})
    ->setName('runs_list');

$app->get(
    '/{source}/{run1}-{run2}/callgraph[{callgraphType}]',
    function ($request, $response, $args) {

        ini_set('max_execution_time', 100);

        /** @var RunsHandler $runsHandler */
        $runsHandler   = $this['handler.runs'];
        $source        = $args['source'];
        $run1          = $runsHandler->getRun($args['run1'], $source);
        $run2          = $runsHandler->getRun($args['run2'], $source);
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

        return $this->view->render($response, 'callgraph.twig', [
            'source' => $source,
            'run'    => $run1->getId() . '-' . $run2->getId(),
            'svg'    => $svg,
        ]);
    }
)
    ->setName('diff_callgraph');

$app->get(
    '/{source}/{run1}-{run2}/[{symbol}]',
    function ($request, $response, $args) {

        /** @var RunsHandler $runsHandler */
        $runsHandler = $this['handler.runs'];
        $source      = $args['source'];
        $run1        = $runsHandler->getRun($args['run1'], $source);
        $run2        = $runsHandler->getRun($args['run2'], $source);
        $run         = $run1->getId() . '-' . $run2->getId();
        $symbol      = $args['symbol'] ?? null;
        $report      = new Report(['source' => $source, 'run' => $run]);
        $report->profilerDiffReport($run1, $run2, $symbol);

        return $this->view->render($response, 'report.twig', [
            'source' => $source,
            'run'    => $run,
            'symbol' => $symbol,
            'body'   => $report->getBody(),
        ]);
    }
)
    ->setName('diff_runs');

$app->get('/{source}/{run}/callgraph[{callgraphType}]', function ($request, $response, $args) {

    ini_set('max_execution_time', 100);

    /** @var RunsHandler $runsHandler */
    $runsHandler = $this['handler.runs'];
    $run         = $runsHandler->getRun($args['run'], $args['source']);

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

    return $this->view->render($response, 'callgraph.twig', [
        'source' => $args['source'],
        'run'    => $run->getId(),
        'svg'    => $svg
    ]);
})
    ->setName('single_callgraph');

$app->get('/{source}/{run}/[{symbol}]', function ($request, $response, $args) {

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

    /** @var RunsHandler $runsHandler */
    $runsHandler = $this['handler.runs'];
    $run         = $runsHandler->getRun($args['run'], $args['source']);
    $report      = new Report(['source' => $args['source'], 'run' => $run->getId()]);
    $symbol      = $args['symbol'] ?? null;
    $report->profilerReport($run, $symbol);

    return $this->view->render($response, 'report.twig', [
        'source' => $args['source'],
        'run'    => $run->getId(),
        'symbol' => $symbol,
        'body'   => $report->getBody(),
    ]);
})
    ->setName('single_run');

$app->run();
