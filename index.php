<?php

namespace Rarst\Sideface;

use Silex\Application;
use UprofilerRuns_Default;

require __DIR__ . '/vendor/autoload.php';
$GLOBALS['UPROFILER_LIB_ROOT'] = __DIR__ . '/uprofiler_lib';

$app = new Application();

$app->get('/', function () {

    require_once $GLOBALS['UPROFILER_LIB_ROOT'] . '/display/uprofiler.php';

    global $params;

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

    ob_start();

    echo "<html>";

    echo "<head><title>uprofiler: Hierarchical Profiler Report</title>";
    uprofiler_include_js_css('/assets');
    echo "</head>";

    echo "<body>";

    $vbar   = ' class="vbar"';
    $vwbar  = ' class="vwbar"';
    $vwlbar = ' class="vwlbar"';
    $vbbar  = ' class="vbbar"';
    $vrbar  = ' class="vrbar"';
    $vgbar  = ' class="vgbar"';

    $uprofiler_runs_impl = new UprofilerRuns_Default();

    displayUprofilerReport(
        $uprofiler_runs_impl,
        $params,
        $GLOBALS['source'],
        $GLOBALS['run'],
        null,
        null,
        null,
        null,
        null
    );


    echo "</body>";
    echo "</html>";

    return ob_get_clean();
});

$app->get('/{source}/{run}', function ($source, $run) {
    require_once $GLOBALS['UPROFILER_LIB_ROOT'] . '/display/uprofiler.php';

    global $params;

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
    echo "<html>";

    echo "<head><title>uprofiler: Hierarchical Profiler Report</title>";
    uprofiler_include_js_css('/assets');
    echo "</head>";

    echo "<body>";
    $uprofiler_runs_impl = new UprofilerRuns_Default();

    displayUprofilerReport(
        $uprofiler_runs_impl,
        $params,
        $source,
        $run,
        null,
        null,
        null,
        null,
        null
    );


    echo "</body>";
    echo "</html>";

    return ob_get_clean();
});

$app->run();
