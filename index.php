<?php

namespace Rarst\Sideface;

use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use uprofilerRuns_Default;

require __DIR__ . '/vendor/autoload.php';

$sort_col         = 'wt';
$diff_mode        = false;
$display_calls    = true;
$stats            = [ ];
$pc_stats         = [ ];
$totals           = 0;
$totals_1         = 0;
$totals_2         = 0;
$metrics          = null;
$sortable_columns = [
    'fn'           => 1,
    'ct'           => 1,
    'wt'           => 1,
    'excl_wt'      => 1,
    'ut'           => 1,
    'excl_ut'      => 1,
    'st'           => 1,
    'excl_st'      => 1,
    'mu'           => 1,
    'excl_mu'      => 1,
    'pmu'          => 1,
    'excl_pmu'     => 1,
    'cpu'          => 1,
    'excl_cpu'     => 1,
    'samples'      => 1,
    'excl_samples' => 1
];
$format_cbk       = [
    'fn'           => '',
    'ct'           => 'uprofiler_count_format',
    'Calls%'       => 'uprofiler_percent_format',
    'wt'           => 'number_format',
    'IWall%'       => 'uprofiler_percent_format',
    'excl_wt'      => 'number_format',
    'EWall%'       => 'uprofiler_percent_format',
    'ut'           => 'number_format',
    'IUser%'       => 'uprofiler_percent_format',
    'excl_ut'      => 'number_format',
    'EUser%'       => 'uprofiler_percent_format',
    'st'           => 'number_format',
    'ISys%'        => 'uprofiler_percent_format',
    'excl_st'      => 'number_format',
    'ESys%'        => 'uprofiler_percent_format',
    'cpu'          => 'number_format',
    'ICpu%'        => 'uprofiler_percent_format',
    'excl_cpu'     => 'number_format',
    'ECpu%'        => 'uprofiler_percent_format',
    'mu'           => 'number_format',
    'IMUse%'       => 'uprofiler_percent_format',
    'excl_mu'      => 'number_format',
    'EMUse%'       => 'uprofiler_percent_format',
    'pmu'          => 'number_format',
    'IPMUse%'      => 'uprofiler_percent_format',
    'excl_pmu'     => 'number_format',
    'EPMUse%'      => 'uprofiler_percent_format',
    'samples'      => 'number_format',
    'ISamples%'    => 'uprofiler_percent_format',
    'excl_samples' => 'number_format',
    'ESamples%'    => 'uprofiler_percent_format',
];

$app = new Application([
    'debug' => true,
]);

$app->register(new TwigServiceProvider(), [
    'twig.path' => __DIR__ . '/twig',
]);

$app->register(new UrlGeneratorServiceProvider());

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

// TODO diff callgraph

$app->get('/{source}/{runId1}-{runId2}/{symbol}', function (Application $app, $source, $runId1, $runId2, $symbol) {

    $run            = $runId1 . '-' . $runId2;
    $runsHandler    = new RunsHandler();
    $run1           = $runsHandler->getRun($runId1, $source);
    $run2           = $runsHandler->getRun($runId2, $source);
    $report         = new Report([ 'source' => $source, 'run' => $run ]);
    $report->init_metrics($run2->getData(), $symbol, 'wt', true);
    $report->profiler_report($symbol, $runId1, $run1->getData(), $runId2, $run2->getData());

    return $app->render('report.twig', [
        'source' => $source,
        'run'    => $run,
        'symbol' => $symbol,
        'body'   => $report->getBody(),
    ]);
})
    ->value('symbol', false)
    ->bind('diff_runs');

$app->get('/{source}/{runId}/callgraph.{callgraphType}', function (Application $app, $source, $runId, $callgraphType) {

    ini_set('max_execution_time', 100);

    $callgraph = new Callgraph([
        'type' => $callgraphType,
    ]);

    $uprofiler_runs_impl = new UprofilerRuns_Default();

//    ob_start();
    $callgraph->render_image($uprofiler_runs_impl, $runId, $source);

    return ''; // TODO wrapper, headers
//    return ob_get_clean();
})
    ->bind('single_callgraph');

$app->get('/{source}/{runId}/{symbol}', function (Application $app, $source, $runId, $symbol) {

    global $wts;

    $runsHandler = new RunsHandler();

    // run may be a single run or a comma separate list of runs
    // that'll be aggregated. If "wts" (a comma separated list
    // of integral weights is specified), the runs will be
    // aggregated in that ratio.
    //
    $runs_array = explode(',', $runId);
    $report     = new Report([ 'source' => $source, 'run' => $runId ]);

    if (count($runs_array) == 1) {
        $run     = $runsHandler->getRun($runs_array[0], $source);
        $runData = $run->getData();
    } else {
        if (! empty( $wts )) {
            $wts_array = explode(",", $wts);
        } else {
            $wts_array = null;
        }
        $data    = $report->aggregate_runs($runsHandler, $runs_array, $wts_array, $source, false);
        $runData = $data['raw'];
    }

    $report->init_metrics($runData, $symbol, 'wt', false);
    $report->profiler_report($symbol, $runId, $runData);

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
