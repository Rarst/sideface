<?php

namespace Rarst\Sideface;

use uprofilerRuns_Default;

require __DIR__ . '/vendor/autoload.php';

$app = new Application([
    'twig.path' => __DIR__ . '/twig',
    'debug'     => true,
]);

$app->get('/{source}', function (Application $app, $source) {

    $runsHandler = new RunsHandler();
    $runsList    = $runsHandler->getRunsList();

    if ($source) {
        $runsList = array_filter($runsList, function ($run) use ($source) {
            /** @var RunInterface $run */
            return $run->getSource() === $source;
        });
    }

    return $app->render('runs-list.twig', [ 'runs' => $runsList, 'source' => $source ]);
})
    ->value('source', false)
    ->bind('runs_list');

$app->get(
    '/{source}/{runId1}-{runId2}/callgraph{callgraphType}',
    function (Application $app, $source, $runId1, $runId2, $callgraphType) {

        ini_set('max_execution_time', 100);

        $callgraph = new Callgraph([
            'type' => $callgraphType ? ltrim($callgraphType, '.') : 'svg',
        ]);

        $uprofiler_runs_impl = new UprofilerRuns_Default();

        if ($callgraphType) {
            $callgraph->render_diff_image($uprofiler_runs_impl, $runId1, $runId2, $source);
            return ''; // TODO wrapper, headers
        }
        ob_start();
        $callgraph->render_diff_image($uprofiler_runs_impl, $runId1, $runId2, $source);
        $svg = ob_get_clean();

        return $app->render('callgraph.twig', [ 'svg' => $svg ]);
    }
)
    ->value('callgraphType', false)
    ->bind('diff_callgraph');

$app->get('/{source}/{runId1}-{runId2}/{symbol}', function (Application $app, $source, $runId1, $runId2, $symbol) {

    $run         = $runId1 . '-' . $runId2;
    $runsHandler = new RunsHandler();
    $run1        = $runsHandler->getRun($runId1, $source);
    $run2        = $runsHandler->getRun($runId2, $source);
    $report      = new Report([ 'source' => $source, 'run' => $run ]);
    $report->profilerDiffReport($run1, $run2, $symbol);

    return $app->render('report.twig', [
        'source' => $source,
        'run'    => $run,
        'symbol' => $symbol,
        'body'   => $report->getBody(),
    ]);
})
    ->value('symbol', false)
    ->bind('diff_runs');

$app->get('/{source}/{runId}/callgraph{callgraphType}', function (Application $app, $source, $runId, $callgraphType) {

    ini_set('max_execution_time', 100);

    $callgraph = new Callgraph([
        'type' => $callgraphType ? ltrim($callgraphType, '.') : 'svg',
    ]);

    $uprofiler_runs_impl = new UprofilerRuns_Default();

    if ($callgraphType) {
        $callgraph->render_image($uprofiler_runs_impl, $runId, $source);
        return ''; // TODO wrapper, headers
    }
    ob_start();
    $callgraph->render_image($uprofiler_runs_impl, $runId, $source);
    $svg = ob_get_clean();

    return $app->render('callgraph.twig', [ 'svg' => $svg ]);
})
    ->value('callgraphType', false)
    ->bind('single_callgraph');

$app->get('/{source}/{runId}/{symbol}', function (Application $app, $source, $runId, $symbol) {

//    global $wts;

    $runsHandler = new RunsHandler();
    $report      = new Report([ 'source' => $source, 'run' => $runId ]);
    $run         = $runsHandler->getRun($runId, $source);

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

    $report->profilerReport($run, $symbol);

    return $app->render('report.twig', [
        'source' => $source,
        'run'    => $runId,
        'symbol' => $symbol,
        'body'   => $report->getBody(),
    ]);
})
    ->value('symbol', false)
    ->bind('single_run');

$app->run();
