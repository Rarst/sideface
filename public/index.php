<?php

namespace Rarst\Sideface;

require __DIR__ . '/../vendor/autoload.php';

$app = new Application([
    'twig.path' => __DIR__ . '/../src/twig',
    'debug'     => true,
]);

$app->get('/{source}', function (Application $app, $source) {

    $runsHandler = $app['handler.runs'];
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
    '/{source}/{run1}-{run2}/callgraph{callgraphType}',
    function (Application $app, Run $run1, Run $run2, $callgraphType) {

        ini_set('max_execution_time', 100);

        $callgraph = new Callgraph([
            'type' => $callgraphType ? ltrim($callgraphType, '.') : 'svg',
        ]);

        if ($callgraphType) {
            $callgraph->render_diff_image($run1, $run2);
            return ''; // TODO wrapper, headers
        }
        ob_start();
        $callgraph->render_diff_image($run1, $run2);
        $svg = ob_get_clean();

        return $app->render('callgraph.twig', [ 'svg' => $svg ]);
    }
)
    ->convert('run1', 'handler.runs:convert')
    ->convert('run2', 'handler.runs:convert')
    ->value('callgraphType', false)
    ->bind('diff_callgraph');

$app->get(
    '/{source}/{run1}-{run2}/{symbol}',
    function (Application $app, $source, RunInterface $run1, RunInterface $run2, $symbol) {

        $run    = $run1->getId() . '-' . $run2->getId();
        $report = new Report([ 'source' => $source, 'run' => $run ]);
        $report->profilerDiffReport($run1, $run2, $symbol);

        return $app->render('report.twig', [
            'source' => $source,
            'run'    => $run,
            'symbol' => $symbol,
            'body'   => $report->getBody(),
        ]);
    }
)
    ->convert('run1', 'handler.runs:convert')
    ->convert('run2', 'handler.runs:convert')
    ->value('symbol', false)
    ->bind('diff_runs');

$app->get(
    '/{source}/{run}/callgraph{callgraphType}',
    function (Application $app, $source, RunInterface $run, $callgraphType) {

        ini_set('max_execution_time', 100);

        $callgraph = new Callgraph([
            'type' => $callgraphType ? ltrim($callgraphType, '.') : 'svg',
        ]);

        if ($callgraphType) {
            $callgraph->render_image($run);
            return ''; // TODO wrapper, headers
        }
        ob_start();
        $callgraph->render_image($run);
        $svg = ob_get_clean();

        return $app->render('callgraph.twig', [
            'source' => $source,
            'run'    => $run->getId(),
            'svg'    => $svg
        ]);
    }
)
    ->convert('run', 'handler.runs:convert')
    ->value('callgraphType', false)
    ->bind('single_callgraph');

$app->get('/{source}/{run}/{symbol}', function (Application $app, $source, RunInterface $run, $symbol) {

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

    $report = new Report([ 'source' => $source, 'run' => $run->getId() ]);
    $report->profilerReport($run, $symbol);

    return $app->render('report.twig', [
        'source' => $source,
        'run'    => $run->getId(),
        'symbol' => $symbol,
        'body'   => $report->getBody(),
    ]);
})
    ->convert('run', 'handler.runs:convert')
    ->value('symbol', false)
    ->bind('single_run');

$app->run();
