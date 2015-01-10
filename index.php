<?php

namespace Rarst\Sideface;

use Silex\Application;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Twig_Environment;
use uprofilerRuns_Default;

require __DIR__ . '/vendor/autoload.php';

/**
 * Type definitions for URL params
 */
define('UPROFILER_STRING_PARAM', 1);
define('UPROFILER_UINT_PARAM', 2);
define('UPROFILER_FLOAT_PARAM', 3);
define('UPROFILER_BOOL_PARAM', 4);

$GLOBALS['UPROFILER_LIB_ROOT'] = __DIR__ . '/uprofiler_lib';

// Supported output format
$uprofiler_legal_image_types = [
    "jpg" => 1,
    "gif" => 1,
    "png" => 1,
    "svg" => 1, // support scalable vector graphic
    "ps"  => 1,
];

/**
 * Our coding convention disallows relative paths in hrefs.
 * Get the base URL path from the SCRIPT_NAME.
 */
$base_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

// default column to sort on -- wall time
$sort_col = "wt";

// default is "single run" report
$diff_mode = false;

// call count data present?
$display_calls = true;

// The following column headers are sortable
$sortable_columns = [
    "fn"           => 1,
    "ct"           => 1,
    "wt"           => 1,
    "excl_wt"      => 1,
    "ut"           => 1,
    "excl_ut"      => 1,
    "st"           => 1,
    "excl_st"      => 1,
    "mu"           => 1,
    "excl_mu"      => 1,
    "pmu"          => 1,
    "excl_pmu"     => 1,
    "cpu"          => 1,
    "excl_cpu"     => 1,
    "samples"      => 1,
    "excl_samples" => 1
];

// Textual descriptions for column headers in "single run" mode
$descriptions = [
    "fn"           => "Function Name",
    "ct"           => "Calls",
    "Calls%"       => "Calls%",
    "wt"           => "Incl. Wall Time<br>(microsec)",
    "IWall%"       => "IWall%",
    "excl_wt"      => "Excl. Wall Time<br>(microsec)",
    "EWall%"       => "EWall%",
    "ut"           => "Incl. User<br>(microsecs)",
    "IUser%"       => "IUser%",
    "excl_ut"      => "Excl. User<br>(microsec)",
    "EUser%"       => "EUser%",
    "st"           => "Incl. Sys <br>(microsec)",
    "ISys%"        => "ISys%",
    "excl_st"      => "Excl. Sys <br>(microsec)",
    "ESys%"        => "ESys%",
    "cpu"          => "Incl. CPU<br>(microsecs)",
    "ICpu%"        => "ICpu%",
    "excl_cpu"     => "Excl. CPU<br>(microsec)",
    "ECpu%"        => "ECPU%",
    "mu"           => "Incl.<br>MemUse<br>(bytes)",
    "IMUse%"       => "IMemUse%",
    "excl_mu"      => "Excl.<br>MemUse<br>(bytes)",
    "EMUse%"       => "EMemUse%",
    "pmu"          => "Incl.<br> PeakMemUse<br>(bytes)",
    "IPMUse%"      => "IPeakMemUse%",
    "excl_pmu"     => "Excl.<br>PeakMemUse<br>(bytes)",
    "EPMUse%"      => "EPeakMemUse%",
    "samples"      => "Incl. Samples",
    "ISamples%"    => "ISamples%",
    "excl_samples" => "Excl. Samples",
    "ESamples%"    => "ESamples%",
];

// Formatting Callback Functions...
$format_cbk = [
    "fn"           => "",
    "ct"           => "uprofiler_count_format",
    "Calls%"       => "uprofiler_percent_format",
    "wt"           => "number_format",
    "IWall%"       => "uprofiler_percent_format",
    "excl_wt"      => "number_format",
    "EWall%"       => "uprofiler_percent_format",
    "ut"           => "number_format",
    "IUser%"       => "uprofiler_percent_format",
    "excl_ut"      => "number_format",
    "EUser%"       => "uprofiler_percent_format",
    "st"           => "number_format",
    "ISys%"        => "uprofiler_percent_format",
    "excl_st"      => "number_format",
    "ESys%"        => "uprofiler_percent_format",
    "cpu"          => "number_format",
    "ICpu%"        => "uprofiler_percent_format",
    "excl_cpu"     => "number_format",
    "ECpu%"        => "uprofiler_percent_format",
    "mu"           => "number_format",
    "IMUse%"       => "uprofiler_percent_format",
    "excl_mu"      => "number_format",
    "EMUse%"       => "uprofiler_percent_format",
    "pmu"          => "number_format",
    "IPMUse%"      => "uprofiler_percent_format",
    "excl_pmu"     => "number_format",
    "EPMUse%"      => "uprofiler_percent_format",
    "samples"      => "number_format",
    "ISamples%"    => "uprofiler_percent_format",
    "excl_samples" => "number_format",
    "ESamples%"    => "uprofiler_percent_format",
];


// Textual descriptions for column headers in "diff" mode
$diff_descriptions = [
    "fn"           => "Function Name",
    "ct"           => "Calls Diff",
    "Calls%"       => "Calls<br>Diff%",
    "wt"           => "Incl. Wall<br>Diff<br>(microsec)",
    "IWall%"       => "IWall<br> Diff%",
    "excl_wt"      => "Excl. Wall<br>Diff<br>(microsec)",
    "EWall%"       => "EWall<br>Diff%",
    "ut"           => "Incl. User Diff<br>(microsec)",
    "IUser%"       => "IUser<br>Diff%",
    "excl_ut"      => "Excl. User<br>Diff<br>(microsec)",
    "EUser%"       => "EUser<br>Diff%",
    "cpu"          => "Incl. CPU Diff<br>(microsec)",
    "ICpu%"        => "ICpu<br>Diff%",
    "excl_cpu"     => "Excl. CPU<br>Diff<br>(microsec)",
    "ECpu%"        => "ECpu<br>Diff%",
    "st"           => "Incl. Sys Diff<br>(microsec)",
    "ISys%"        => "ISys<br>Diff%",
    "excl_st"      => "Excl. Sys Diff<br>(microsec)",
    "ESys%"        => "ESys<br>Diff%",
    "mu"           => "Incl.<br>MemUse<br>Diff<br>(bytes)",
    "IMUse%"       => "IMemUse<br>Diff%",
    "excl_mu"      => "Excl.<br>MemUse<br>Diff<br>(bytes)",
    "EMUse%"       => "EMemUse<br>Diff%",
    "pmu"          => "Incl.<br> PeakMemUse<br>Diff<br>(bytes)",
    "IPMUse%"      => "IPeakMemUse<br>Diff%",
    "excl_pmu"     => "Excl.<br>PeakMemUse<br>Diff<br>(bytes)",
    "EPMUse%"      => "EPeakMemUse<br>Diff%",
    "samples"      => "Incl. Samples Diff",
    "ISamples%"    => "ISamples Diff%",
    "excl_samples" => "Excl. Samples Diff",
    "ESamples%"    => "ESamples Diff%",
];

// columns that'll be displayed in a top-level report
$stats = [ ];

// columns that'll be displayed in a function's parent/child report
$pc_stats = [ ];

// Various total counts
$totals   = 0;
$totals_1 = 0;
$totals_2 = 0;

/*
 * The subset of $possible_metrics that is present in the raw profile data.
 */
$metrics = null;

// param name, its type, and default value
$params = [
    'run'    => [ UPROFILER_STRING_PARAM, '' ],
    'wts'    => [ UPROFILER_STRING_PARAM, '' ],
    'symbol' => [ UPROFILER_STRING_PARAM, '' ],
    'sort'   => [ UPROFILER_STRING_PARAM, 'wt' ], // wall time
    'run1'   => [ UPROFILER_STRING_PARAM, '' ],
    'run2'   => [ UPROFILER_STRING_PARAM, '' ],
    'source' => [ UPROFILER_STRING_PARAM, 'uprofiler' ],
    'all'    => [ UPROFILER_UINT_PARAM, 0 ],
];

// pull values of these params, and create named globals for each param
uprofiler_param_init($params);

/* reset params to be a array of variable names to values
   by the end of this page, param should only contain values that need
   to be preserved for the next page. unset all unwanted keys in $params.
 */
foreach ($params as $k => $v) {
    $params[$k] = $$k;
    // unset key from params that are using default values. So URLs aren't
    // ridiculously long.
    if ($params[$k] == $v[1]) {
        unset( $params[$k] );
    }
}

$vbar   = ' class="vbar"';
$vwbar  = ' class="vwbar"';
$vwlbar = ' class="vwlbar"';
$vbbar  = ' class="vbbar"';
$vrbar  = ' class="vrbar"';
$vgbar  = ' class="vgbar"';

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

$app->get('/{source}/{run1}-{run2}', function (Application $app, $source, $run1, $run2) {

    global $params, $symbol, $sort;

    $params['run1'] = $run1;
    $params['run2'] = $run2;
    $runsHandler    = new RunsHandler();
    $data1          = $runsHandler->getRun($run1, $source);
    $data2          = $runsHandler->getRun($run2, $source);
    $report         = new Report();
    $report->init_metrics($data2, $symbol, $sort, true);

    ob_start();
    profiler_report($params, $symbol, $sort, $run1, '', $data1, $run2, '', $data2);
    $body = ob_get_clean();

    /** @var Twig_Environment $twig */
    $twig = $app['twig'];
    return $twig->render('index.twig', [ 'body' => $body ]);
})
    ->bind('diff_runs');

$app->get('/{source}/{run}', function (Application $app, $source, $run) {

    global $params, $wts, $symbol, $sort;

    $runsHandler = new RunsHandler();

    // run may be a single run or a comma separate list of runs
    // that'll be aggregated. If "wts" (a comma separated list
    // of integral weights is specified), the runs will be
    // aggregated in that ratio.
    //
    $runs_array = explode(",", $run);

    if (count($runs_array) == 1) {
        $uprofiler_data = $runsHandler->getRun($runs_array[0], $source);
    } else {
        if (! empty( $wts )) {
            $wts_array = explode(",", $wts);
        } else {
            $wts_array = null;
        }
        $data           = uprofiler_aggregate_runs(
            $runsHandler,
            $runs_array,
            $wts_array,
            $source,
            false
        );
        $uprofiler_data = $data['raw'];
    }

    $report = new Report();
    $report->init_metrics($uprofiler_data, $symbol, $sort, false);
    $report->profiler_report($params, $symbol, $sort, $run, '', $uprofiler_data);

    /** @var Twig_Environment $twig */
    $twig = $app['twig'];
    return $twig->render('report.twig', [
        'source' => $source,
        'run'    => $run,
        'body'   => $report->getBody(),
    ]);
})
    ->bind('single_run');

$app->run();
