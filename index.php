<?php

namespace Rarst\Sideface;

use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Twig_Environment;
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

    /** @var Twig_Environment $twig */
    $twig        = $app['twig'];
    $runsHandler = new RunsHandler();
    $runsList    = $runsHandler->getRunsList();

    if ($source) {
        $runsList = array_filter($runsList, function ($run) use ($source) {
            return $run['source'] === $source;
        });
    }

    return $twig->render('runs-list.twig', [ 'runs' => $runsList, 'source' => $source ]);
})
    ->value('source', false)
    ->bind('runs_list');

$app->get('/{source}/{run1}-{run2}/{symbol}', function (Application $app, $source, $run1, $run2, $symbol) {

    global $params, $sort;

    $params['run1'] = $run1;
    $params['run2'] = $run2;
    $run            = $run1 . '-' . $run2;
    $runsHandler    = new RunsHandler();
    $data1          = $runsHandler->getRun($run1, $source);
    $data2          = $runsHandler->getRun($run2, $source);
    $report         = new Report([ 'source' => $source, 'run' => $run ]);
    $report->init_metrics($data2, $symbol, $sort, true);
    $report->profiler_report($symbol, $run1, $data1, $run2, $data2);

    /** @var Twig_Environment $twig */
    $twig = $app['twig'];
    return $twig->render('report.twig', [
        'source' => $source,
        'run'    => $run,
        'symbol' => $symbol,
        'body'   => $report->getBody(),
    ]);
})
    ->value('symbol', false)
    ->bind('diff_runs');

$app->get('/{source}/{run}/callgraph.{callgraphType}', function (Application $app, $source, $run, $callgraphType) {

    global $run1, $run2;

    ini_set('max_execution_time', 100);

    $callgraph = new Callgraph([
        'type' => $callgraphType,
    ]);

    $uprofiler_runs_impl = new UprofilerRuns_Default();

//    ob_start();
    if (! empty( $run )) {
        $callgraph->render_image($uprofiler_runs_impl, $run, $source);
    } else {
        $callgraph->render_diff_image($uprofiler_runs_impl, $run1, $run2, $source);
    }
    return ''; // TODO wrapper, headers
//    return ob_get_clean();
})
    ->bind('single_callgraph');

$app->get('/{source}/{run}/{symbol}', function (Application $app, $source, $run, $symbol) {

    global $wts, $sort;

    $runsHandler = new RunsHandler();

    // run may be a single run or a comma separate list of runs
    // that'll be aggregated. If "wts" (a comma separated list
    // of integral weights is specified), the runs will be
    // aggregated in that ratio.
    //
    $runs_array = explode(",", $run);
    $report     = new Report([ 'source' => $source, 'run' => $run ]);

    if (count($runs_array) == 1) {
        $uprofiler_data = $runsHandler->getRun($runs_array[0], $source);
    } else {
        if (! empty( $wts )) {
            $wts_array = explode(",", $wts);
        } else {
            $wts_array = null;
        }
        $data           = $report->aggregate_runs($runsHandler, $runs_array, $wts_array, $source, false);
        $uprofiler_data = $data['raw'];
    }

    $report->init_metrics($uprofiler_data, $symbol, $sort, false);
    $report->profiler_report($symbol, $run, $uprofiler_data);

    /** @var Twig_Environment $twig */
    $twig = $app['twig'];
    return $twig->render('report.twig', [
        'source' => $source,
        'run'    => $run,
        'symbol' => $symbol,
        'body'   => $report->getBody(),
    ]);
})
    ->value('symbol', false)
    ->bind('single_run');

$app->run();
