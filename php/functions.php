<?php

/**
 * Formats call counts for uprofiler reports.
 *
 * Description:
 * Call counts in single-run reports are integer values.
 * However, call counts for aggregated reports can be
 * fractional. This function will print integer values
 * without decimal point, but with commas etc.
 *
 *   4000 ==> 4,000
 *
 * It'll round fractional values to decimal precision of 3
 *   4000.1212 ==> 4,000.121
 *   4000.0001 ==> 4,000
 *
 * @param $num
 *
 * @return string
 */
function uprofiler_count_format($num)
{
    $num = round($num, 3);
    if (round($num) == $num) {
        return number_format($num);
    } else {
        return number_format($num, 3);
    }
}

/**
 * @param     $s
 * @param int $precision
 *
 * @return string
 */
function uprofiler_percent_format($s, $precision = 1)
{
    return sprintf('%.' . $precision . 'f%%', 100 * $s);
}

/**
 * Callback comparison operator (passed to usort() for sorting array of
 * tuples) that compares array elements based on the sort column
 * specified in $sort_col (global parameter).
 *
 * @author Kannan
 */
function sort_cbk($a, $b)
{
    global $sort_col;
    global $diff_mode;

    if ($sort_col == "fn") {
        // case insensitive ascending sort for function names
        $left  = strtoupper($a["fn"]);
        $right = strtoupper($b["fn"]);

        if ($left == $right) {
            return 0;
        }
        return ( $left < $right ) ? - 1 : 1;
    } else {

        // descending sort for all others
        $left  = $a[$sort_col];
        $right = $b[$sort_col];

        // if diff mode, sort by absolute value of regression/improvement
        if ($diff_mode) {
            $left  = abs($left);
            $right = abs($right);
        }

        if ($left == $right) {
            return 0;
        }
        return ( $left > $right ) ? - 1 : 1;
    }
}

/**
 * Get the appropriate description for a statistic
 * (depending upon whether we are in diff report mode
 * or single run report mode).
 *
 * @author Kannan
 */
function stat_description($stat)
{
    global $descriptions;
    global $diff_descriptions;
    global $diff_mode;

    if ($diff_mode) {
        return $diff_descriptions[$stat];
    } else {
        return $descriptions[$stat];
    }
}

/**
 * Computes percentage for a pair of values, and returns it
 * in string format.
 */
function pct($a, $b)
{
    if ($b == 0) {
        return "N/A";
    } else {
        $res = ( round(( $a * 1000 / $b )) / 10 );
        return $res;
    }
}

/**
 * Given a number, returns the td class to use for display.
 *
 * For instance, negative numbers in diff reports comparing two runs (run1 & run2)
 * represent improvement from run1 to run2. We use green to display those deltas,
 * and red for regression deltas.
 */
function get_print_class($num, $bold)
{
    global $vbar;
    global $vbbar;
    global $vrbar;
    global $vgbar;
    global $diff_mode;

    if ($bold) {
        if ($diff_mode) {
            if ($num <= 0) {
                $class = $vgbar; // green (improvement)
            } else {
                $class = $vrbar; // red (regression)
            }
        } else {
            $class = $vbbar; // blue
        }
    } else {
        $class = $vbar;  // default (black)
    }

    return $class;
}

/**
 * Return attribute names and values to be used by javascript tooltip.
 */
function get_tooltip_attributes($type, $metric)
{
    return "type='$type' metric='$metric'";
}

function uprofiler_error($message)
{
    error_log($message);
}

/*
 * The list of possible metrics collected as part of uprofiler that
 * require inclusive/exclusive handling while reporting.
 *
 * @author Kannan
 */
function uprofiler_get_possible_metrics()
{
    static $possible_metrics =
    [
        "wt"      => [ "Wall", "microsecs", "walltime" ],
        "ut"      => [ "User", "microsecs", "user cpu time" ],
        "st"      => [ "Sys", "microsecs", "system cpu time" ],
        "cpu"     => [ "Cpu", "microsecs", "cpu time" ],
        "mu"      => [ "MUse", "bytes", "memory usage" ],
        "pmu"     => [ "PMUse", "bytes", "peak memory usage" ],
        "samples" => [ "Samples", "samples", "cpu time" ]
    ];
    return $possible_metrics;
}

/*
 * Get the list of metrics present in $uprofiler_data as an array.
 *
 * @author Kannan
 */
function uprofiler_get_metrics($uprofiler_data)
{

    // get list of valid metrics
    $possible_metrics = uprofiler_get_possible_metrics();

    // return those that are present in the raw data.
    // We'll just look at the root of the subtree for this.
    $metrics = [ ];
    foreach ($possible_metrics as $metric => $desc) {
        if (isset( $uprofiler_data["main()"][$metric] )) {
            $metrics[] = $metric;
        }
    }

    return $metrics;
}

/**
 * Takes a parent/child function name encoded as
 * "a==>b" and returns array("a", "b").
 *
 * @author Kannan
 */
function uprofiler_parse_parent_child($parent_child)
{
    $ret = explode("==>", $parent_child);

    // Return if both parent and child are set
    if (isset( $ret[1] )) {
        return $ret;
    }

    return [ null, $ret[0] ];
}

/**
 * Given parent & child function name, composes the key
 * in the format present in the raw data.
 *
 * @author Kannan
 */
function uprofiler_build_parent_child_key($parent, $child)
{
    if ($parent) {
        return $parent . "==>" . $child;
    } else {
        return $child;
    }
}


/**
 * Checks if uprofiler raw data appears to be valid and not corrupted.
 *
 * @param   int   $run_id          Run id of run to be pruned.
 *                                 [Used only for reporting errors.]
 * @param   array $raw_data        uprofiler raw data to be pruned
 *                                 & validated.
 *
 * @return  bool   true on success, false on failure
 *
 * @author Kannan
 */
function uprofiler_valid_run($run_id, $raw_data)
{

    $main_info = $raw_data["main()"];
    if (empty( $main_info )) {
        uprofiler_error("uprofiler: main() missing in raw data for Run ID: $run_id");
        return false;
    }

    // raw data should contain either wall time or samples information...
    if (isset( $main_info["wt"] )) {
        $metric = "wt";
    } else if (isset( $main_info["samples"] )) {
        $metric = "samples";
    } else {
        uprofiler_error("uprofiler: Wall Time information missing from Run ID: $run_id");
        return false;
    }

    foreach ($raw_data as $info) {
        $val = $info[$metric];

        // basic sanity checks...
        if ($val < 0) {
            uprofiler_error("uprofiler: $metric should not be negative: Run ID $run_id"
                            . serialize($info));
            return false;
        }
        if ($val > ( 86400000000 )) {
            uprofiler_error("uprofiler: $metric > 1 day found in Run ID: $run_id "
                            . serialize($info));
            return false;
        }
    }
    return true;
}


/**
 * Return a trimmed version of the uprofiler raw data. Note that the raw
 * data contains one entry for each unique parent/child function
 * combination.The trimmed version of raw data will only contain
 * entries where either the parent or child function is in the list
 * of $functions_to_keep.
 *
 * Note: Function main() is also always kept so that overall totals
 * can still be obtained from the trimmed version.
 *
 * @param  array  uprofiler raw data
 * @param  array  array of function names
 *
 * @return array  Trimmed uprofiler Report
 *
 * @author Kannan
 */
function uprofiler_trim_run($raw_data, $functions_to_keep)
{

    // convert list of functions to a hash with function as the key
    $function_map = array_fill_keys($functions_to_keep, 1);

    // always keep main() as well so that overall totals can still
    // be computed if need be.
    $function_map['main()'] = 1;

    $new_raw_data = [ ];
    foreach ($raw_data as $parent_child => $info) {
        list( $parent, $child ) = uprofiler_parse_parent_child($parent_child);

        if (isset( $function_map[$parent] ) || isset( $function_map[$child] )) {
            $new_raw_data[$parent_child] = $info;
        }
    }

    return $new_raw_data;
}

/**
 * Takes raw uprofiler data that was aggregated over "$num_runs" number
 * of runs averages/normalizes the data. Essentially the various metrics
 * collected are divided by $num_runs.
 *
 * @author Kannan
 */
function uprofiler_normalize_metrics($raw_data, $num_runs)
{

    if (empty( $raw_data ) || ( $num_runs == 0 )) {
        return $raw_data;
    }

    $raw_data_total = [ ];

    if (isset( $raw_data["==>main()"] ) && isset( $raw_data["main()"] )) {
        uprofiler_error("uprofiler Error: both ==>main() and main() set in raw data...");
    }

    foreach ($raw_data as $parent_child => $info) {
        foreach ($info as $metric => $value) {
            $raw_data_total[$parent_child][$metric] = ( $value / $num_runs );
        }
    }

    return $raw_data_total;
}


/**
 * Get raw data corresponding to specified array of runs
 * aggregated by certain weightage.
 *
 * Suppose you have run:5 corresponding to page1.php,
 *                  run:6 corresponding to page2.php,
 *             and  run:7 corresponding to page3.php
 *
 * and you want to accumulate these runs in a 2:4:1 ratio. You
 * can do so by calling:
 *
 *     uprofiler_aggregate_runs(array(5, 6, 7), array(2, 4, 1));
 *
 * The above will return raw data for the runs aggregated
 * in 2:4:1 ratio.
 *
 * @param object  $uprofiler_runs_impl An object that implements
 *                                     the iUprofilerRuns interface
 * @param  array  $runs                run ids of the uprofiler runs..
 * @param  array  $wts                 integral (ideally) weights for $runs
 * @param  string $source              source to fetch raw data for run from
 * @param  bool   $use_script_name     If true, a fake edge from main() to
 *                                     to __script::<scriptname> is introduced
 *                                     in the raw data so that after aggregations
 *                                     the script name is still preserved.
 *
 * @return array  Return aggregated raw data
 *
 * @author Kannan
 */
function uprofiler_aggregate_runs(
    $uprofiler_runs_impl,
    $runs,
    $wts,
    $source = "phprof",
    $use_script_name = false
) {

    $raw_data_total = null;
    $raw_data       = null;
    $metrics        = [ ];

    $run_count = count($runs);
    $wts_count = count($wts);

    if (( $run_count == 0 ) ||
        ( ( $wts_count > 0 ) && ( $run_count != $wts_count ) )
    ) {
        return [
            'description' => 'Invalid input..',
            'raw'         => null
        ];
    }

    $bad_runs = [ ];
    foreach ($runs as $idx => $run_id) {

        $raw_data = $uprofiler_runs_impl->get_run($run_id, $source, $description);

        // use the first run to derive what metrics to aggregate on.
        if ($idx == 0) {
            foreach ($raw_data["main()"] as $metric => $val) {
                if ($metric != "pmu") {
                    // for now, just to keep data size small, skip "peak" memory usage
                    // data while aggregating.
                    // The "regular" memory usage data will still be tracked.
                    if (isset( $val )) {
                        $metrics[] = $metric;
                    }
                }
            }
        }

        if (! uprofiler_valid_run($run_id, $raw_data)) {
            $bad_runs[] = $run_id;
            continue;
        }

        if ($use_script_name) {
            $page = $description;

            // create a fake function '__script::$page', and have and edge from
            // main() to '__script::$page'. We will also need edges to transfer
            // all edges originating from main() to now originate from
            // '__script::$page' to all function called from main().
            //
            // We also weight main() ever so slightly higher so that
            // it shows up above the new entry in reports sorted by
            // inclusive metrics or call counts.
            if ($page) {
                foreach ($raw_data["main()"] as $metric => $val) {
                    $fake_edge[$metric] = $val;
                    $new_main[$metric]  = $val + 0.00001;
                }
                $raw_data["main()"] = $new_main;
                $raw_data[uprofiler_build_parent_child_key("main()",
                    "__script::$page")]
                                    = $fake_edge;
            } else {
                $use_script_name = false;
            }
        }

        // if no weights specified, use 1 as the default weightage..
        $wt = ( $wts_count == 0 ) ? 1 : $wts[$idx];

        // aggregate $raw_data into $raw_data_total with appropriate weight ($wt)
        foreach ($raw_data as $parent_child => $info) {
            if ($use_script_name) {
                // if this is an old edge originating from main(), it now
                // needs to be from '__script::$page'
                if (substr($parent_child, 0, 9) == "main()==>") {
                    $child = substr($parent_child, 9);
                    // ignore the newly added edge from main()
                    if (substr($child, 0, 10) != "__script::") {
                        $parent_child = uprofiler_build_parent_child_key("__script::$page",
                            $child);
                    }
                }
            }

            if (! isset( $raw_data_total[$parent_child] )) {
                foreach ($metrics as $metric) {
                    $raw_data_total[$parent_child][$metric] = ( $wt * $info[$metric] );
                }
            } else {
                foreach ($metrics as $metric) {
                    $raw_data_total[$parent_child][$metric] += ( $wt * $info[$metric] );
                }
            }
        }
    }

    $runs_string = implode(",", $runs);

    if (isset( $wts )) {
        $wts_string          = "in the ratio (" . implode(":", $wts) . ")";
        $normalization_count = array_sum($wts);
    } else {
        $wts_string          = "";
        $normalization_count = $run_count;
    }

    $run_count = $run_count - count($bad_runs);

    $data['description'] = "Aggregated Report for $run_count runs: " .
                           "$runs_string $wts_string\n";
    $data['raw']         = uprofiler_normalize_metrics($raw_data_total,
        $normalization_count);
    $data['bad_runs']    = $bad_runs;

    return $data;
}


/**
 * Analyze hierarchical raw data, and compute per-function (flat)
 * inclusive and exclusive metrics.
 *
 * Also, store overall totals in the 2nd argument.
 *
 * @param  array $raw_data          uprofiler format raw profiler data.
 * @param  array &$overall_totals   OUT argument for returning
 *                                  overall totals for various
 *                                  metrics.
 *
 * @return array Returns a map from function name to its
 *               call count and inclusive & exclusive metrics
 *               (such as wall time, etc.).
 *
 * @author Kannan Muthukkaruppan
 */
function uprofiler_compute_flat_info($raw_data, &$overall_totals)
{

    global $display_calls;

    $metrics = uprofiler_get_metrics($raw_data);

    $overall_totals = [
        "ct"      => 0,
        "wt"      => 0,
        "ut"      => 0,
        "st"      => 0,
        "cpu"     => 0,
        "mu"      => 0,
        "pmu"     => 0,
        "samples" => 0
    ];

    // compute inclusive times for each function
    $symbol_tab = uprofiler_compute_inclusive_times($raw_data);

    /* total metric value is the metric value for "main()" */
    foreach ($metrics as $metric) {
        $overall_totals[$metric] = $symbol_tab["main()"][$metric];
    }

    /*
     * initialize exclusive (self) metric value to inclusive metric value
     * to start with.
     * In the same pass, also add up the total number of function calls.
     */
    foreach ($symbol_tab as $symbol => $info) {
        foreach ($metrics as $metric) {
            $symbol_tab[$symbol]["excl_" . $metric] = $symbol_tab[$symbol][$metric];
        }
        if ($display_calls) {
            /* keep track of total number of calls */
            $overall_totals["ct"] += $info["ct"];
        }
    }

    /* adjust exclusive times by deducting inclusive time of children */
    foreach ($raw_data as $parent_child => $info) {
        list( $parent, $child ) = uprofiler_parse_parent_child($parent_child);

        if ($parent) {
            foreach ($metrics as $metric) {
                // make sure the parent exists hasn't been pruned.
                if (isset( $symbol_tab[$parent] )) {
                    $symbol_tab[$parent]["excl_" . $metric] -= $info[$metric];
                }
            }
        }
    }

    return $symbol_tab;
}

/**
 * Hierarchical diff:
 * Compute and return difference of two call graphs: Run2 - Run1.
 *
 * @author Kannan
 */
function uprofiler_compute_diff($uprofiler_data1, $uprofiler_data2)
{
    global $display_calls;

    // use the second run to decide what metrics we will do the diff on
    $metrics = uprofiler_get_metrics($uprofiler_data2);

    $uprofiler_delta = $uprofiler_data2;

    foreach ($uprofiler_data1 as $parent_child => $info) {

        if (! isset( $uprofiler_delta[$parent_child] )) {

            // this pc combination was not present in run1;
            // initialize all values to zero.
            if ($display_calls) {
                $uprofiler_delta[$parent_child] = [ "ct" => 0 ];
            } else {
                $uprofiler_delta[$parent_child] = [ ];
            }
            foreach ($metrics as $metric) {
                $uprofiler_delta[$parent_child][$metric] = 0;
            }
        }

        if ($display_calls) {
            $uprofiler_delta[$parent_child]["ct"] -= $info["ct"];
        }

        foreach ($metrics as $metric) {
            $uprofiler_delta[$parent_child][$metric] -= $info[$metric];
        }
    }

    return $uprofiler_delta;
}


/**
 * Compute inclusive metrics for function. This code was factored out
 * of uprofiler_compute_flat_info().
 *
 * The raw data contains inclusive metrics of a function for each
 * unique parent function it is called from. The total inclusive metrics
 * for a function is therefore the sum of inclusive metrics for the
 * function across all parents.
 *
 * @return array  Returns a map of function name to total (across all parents)
 *                inclusive metrics for the function.
 *
 * @author Kannan
 */
function uprofiler_compute_inclusive_times($raw_data)
{
    global $display_calls;

    $metrics = uprofiler_get_metrics($raw_data);

    $symbol_tab = [ ];

    /*
     * First compute inclusive time for each function and total
     * call count for each function across all parents the
     * function is called from.
     */
    foreach ($raw_data as $parent_child => $info) {

        list( $parent, $child ) = uprofiler_parse_parent_child($parent_child);

        if ($parent == $child) {
            /*
             * uprofiler PHP extension should never trigger this situation any more.
             * Recursion is handled in the uprofiler PHP extension by giving nested
             * calls a unique recursion-depth appended name (for example, foo@1).
             */
            uprofiler_error("Error in Raw Data: parent & child are both: $parent");
            return;
        }

        if (! isset( $symbol_tab[$child] )) {

            if ($display_calls) {
                $symbol_tab[$child] = [ "ct" => $info["ct"] ];
            } else {
                $symbol_tab[$child] = [ ];
            }
            foreach ($metrics as $metric) {
                $symbol_tab[$child][$metric] = $info[$metric];
            }
        } else {
            if ($display_calls) {
                /* increment call count for this child */
                $symbol_tab[$child]["ct"] += $info["ct"];
            }

            /* update inclusive times/metric for this child  */
            foreach ($metrics as $metric) {
                $symbol_tab[$child][$metric] += $info[$metric];
            }
        }
    }

    return $symbol_tab;
}

/**
 * Set one key in an array and return the array
 *
 * @author Kannan
 */
function uprofiler_array_set($arr, $k, $v)
{
    $arr[$k] = $v;
    return $arr;
}

/**
 * Removes/unsets one key in an array and return the array
 *
 * @author Kannan
 */
function uprofiler_array_unset($arr, $k)
{
    unset( $arr[$k] );
    return $arr;
}

/**
 * Internal helper function used by various
 * uprofiler_get_param* flavors for various
 * types of parameters.
 *
 * @param string   name of the URL query string param
 *
 * @author Kannan
 */
function uprofiler_get_param_helper($param)
{
    $val = null;
    if (isset( $_GET[$param] )) {
        $val = $_GET[$param];
    } else if (isset( $_POST[$param] )) {
        $val = $_POST[$param];
    }
    return $val;
}

/**
 * Extracts value for string param $param from query
 * string. If param is not specified, return the
 * $default value.
 *
 * @author Kannan
 */
function uprofiler_get_string_param($param, $default = '')
{
    $val = uprofiler_get_param_helper($param);

    if ($val === null) {
        return $default;
    }

    return $val;
}

/**
 * Extracts value for unsigned integer param $param from
 * query string. If param is not specified, return the
 * $default value.
 *
 * If value is not a valid unsigned integer, logs error
 * and returns null.
 *
 * @author Kannan
 */
function uprofiler_get_uint_param($param, $default = 0)
{
    $val = uprofiler_get_param_helper($param);

    if ($val === null) {
        $val = $default;
    }

    // trim leading/trailing whitespace
    $val = trim($val);

    // if it only contains digits, then ok..
    if (ctype_digit($val)) {
        return $val;
    }

    uprofiler_error("$param is $val. It must be an unsigned integer.");
    return null;
}


/**
 * Extracts value for a float param $param from
 * query string. If param is not specified, return
 * the $default value.
 *
 * If value is not a valid unsigned integer, logs error
 * and returns null.
 *
 * @author Kannan
 */
function uprofiler_get_float_param($param, $default = 0)
{
    $val = uprofiler_get_param_helper($param);

    if ($val === null) {
        $val = $default;
    }

    // trim leading/trailing whitespace
    $val = trim($val);

    // TBD: confirm the value is indeed a float.
    if (true) // for now..
    {
        return (float) $val;
    }

    uprofiler_error("$param is $val. It must be a float.");
    return null;
}

/**
 * Extracts value for a boolean param $param from
 * query string. If param is not specified, return
 * the $default value.
 *
 * If value is not a valid unsigned integer, logs error
 * and returns null.
 *
 * @author Kannan
 */
function uprofiler_get_bool_param($param, $default = false)
{
    $val = uprofiler_get_param_helper($param);

    if ($val === null) {
        $val = $default;
    }

    // trim leading/trailing whitespace
    $val = trim($val);

    switch (strtolower($val)) {
        case '0':
        case '1':
            $val = (bool) $val;
            break;
        case 'true':
        case 'on':
        case 'yes':
            $val = true;
            break;
        case 'false':
        case 'off':
        case 'no':
            $val = false;
            break;
        default:
            uprofiler_error("$param is $val. It must be a valid boolean string.");
            return null;
    }

    return $val;

}

/**
 * Initialize params from URL query string. The function
 * creates globals variables for each of the params
 * and if the URL query string doesn't specify a particular
 * param initializes them with the corresponding default
 * value specified in the input.
 *
 * @params array $params An array whose keys are the names
 *                       of URL params who value needs to
 *                       be retrieved from the URL query
 *                       string. PHP globals are created
 *                       with these names. The value is
 *                       itself an array with 2-elems (the
 *                       param type, and its default value).
 *                       If a param is not specified in the
 *                       query string the default value is
 *                       used.
 *
 * @author Kannan
 */
function uprofiler_param_init($params)
{
    /* Create variables specified in $params keys, init defaults */
    foreach ($params as $k => $v) {
        switch ($v[0]) {
            case UPROFILER_STRING_PARAM:
                $p = uprofiler_get_string_param($k, $v[1]);
                break;
            case UPROFILER_UINT_PARAM:
                $p = uprofiler_get_uint_param($k, $v[1]);
                break;
            case UPROFILER_FLOAT_PARAM:
                $p = uprofiler_get_float_param($k, $v[1]);
                break;
            case UPROFILER_BOOL_PARAM:
                $p = uprofiler_get_bool_param($k, $v[1]);
                break;
            default:
                uprofiler_error("Invalid param type passed to uprofiler_param_init: "
                                . $v[0]);
                exit();
        }

        if ($k === 'run') {
            $p = implode(',', array_filter(explode(',', $p), 'ctype_xdigit'));
        }

        // create a global variable using the parameter name.
        $GLOBALS[$k] = $p;
    }
}


/**
 * Given a partial query string $q return matching function names in
 * specified uprofiler run. This is used for the type ahead function
 * selector.
 *
 * @author Kannan
 */
function uprofiler_get_matching_functions($q, $uprofiler_data)
{

    $matches = [ ];

    foreach ($uprofiler_data as $parent_child => $info) {
        list( $parent, $child ) = uprofiler_parse_parent_child($parent_child);
        if (stripos($parent, $q) !== false) {
            $matches[$parent] = 1;
        }
        if (stripos($child, $q) !== false) {
            $matches[$child] = 1;
        }
    }

    $res = array_keys($matches);

    // sort it so the answers are in some reliable order...
    asort($res);

    return ( $res );
}


/**
 * Send an HTTP header with the response. You MUST use this function instead
 * of header() so that we can debug header issues because they're virtually
 * impossible to debug otherwise. If you try to commit header(), SVN will
 * reject your commit.
 *
 * @param string  HTTP header name, like 'Location'
 * @param string  HTTP header value, like 'http://www.example.com/'
 *
 */
function uprofiler_http_header($name, $value)
{

    if (! $name) {
        uprofiler_error('http_header usage');
        return null;
    }

    if (! is_string($value)) {
        uprofiler_error('http_header value not a string');
    }

    header($name . ': ' . $value, true);
}

/**
 * Generate and send MIME header for the output image to client browser.
 *
 * @author cjiang
 */
function uprofiler_generate_mime_header($type, $length)
{
    switch ($type) {
        case 'jpg':
            $mime = 'image/jpeg';
            break;
        case 'gif':
            $mime = 'image/gif';
            break;
        case 'png':
            $mime = 'image/png';
            break;
        case 'svg':
            $mime = 'image/svg+xml'; // content type for scalable vector graphic
            break;
        case 'ps':
            $mime = 'application/postscript';
            break;
        default:
            $mime = false;
    }

    if ($mime) {
        uprofiler_http_header('Content-type', $mime);
        uprofiler_http_header('Content-length', (string) $length);
    }
}

/**
 * Generate image according to DOT script. This function will spawn a process
 * with "dot" command and pipe the "dot_script" to it and pipe out the
 * generated image content.
 *
 * @param dot_script , string, the script for DOT to generate the image.
 * @param type       , one of the supported image types, see
 *                   $uprofiler_legal_image_types.
 * @returns, binary content of the generated image on success. empty string on
 *                   failure.
 *
 * @author cjiang
 */
function uprofiler_generate_image_by_dot($dot_script, $type)
{
    $descriptorspec = [
        // stdin is a pipe that the child will read from
        0 => [ "pipe", "r" ],
        // stdout is a pipe that the child will write to
        1 => [ "pipe", "w" ],
        // stderr is a pipe that the child will write to
        2 => [ "pipe", "w" ]
    ];

    $cmd = " dot -T" . $type;

    $process = proc_open($cmd, $descriptorspec, $pipes, sys_get_temp_dir(), [ 'PATH' => getenv('PATH') ]);
    if (is_resource($process)) {
        fwrite($pipes[0], $dot_script);
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);

        $err = stream_get_contents($pipes[2]);
        if (! empty( $err )) {
            print "failed to execute cmd: \"$cmd\". stderr: `$err'\n";
            exit;
        }

        fclose($pipes[2]);
        fclose($pipes[1]);
        proc_close($process);
        return $output;
    }
    print "failed to execute cmd \"$cmd\"";
    exit();
}

/*
 * Get the children list of all nodes.
 */
function uprofiler_get_children_table($raw_data)
{
    $children_table = [ ];
    foreach ($raw_data as $parent_child => $info) {
        list( $parent, $child ) = uprofiler_parse_parent_child($parent_child);
        if (! isset( $children_table[$parent] )) {
            $children_table[$parent] = [ $child ];
        } else {
            $children_table[$parent][] = $child;
        }
    }
    return $children_table;
}

/**
 * Generate DOT script from the given raw phprof data.
 *
 * @param raw_data             , phprof profile data.
 * @param threshold            , float, the threshold value [0,1). The functions in the
 *                             raw_data whose exclusive wall times ratio are below the
 *                             threshold will be filtered out and won't apprear in the
 *                             generated image.
 * @param page                 , string(optional), the root node name. This can be used to
 *                             replace the 'main()' as the root node.
 * @param func                 , string, the focus function.
 * @param critical_path        , bool, whether or not to display critical path with
 *                             bold lines.
 * @returns, string, the DOT script to generate image.
 *
 * @author cjiang
 */
function uprofiler_generate_dot_script(
    $raw_data,
    $threshold,
    $source,
    $page,
    $func,
    $critical_path,
    $right = null,
    $left = null
) {

    $max_width        = 5;
    $max_height       = 3.5;
    $max_fontsize     = 35;
    $max_sizing_ratio = 20;

    $totals = [ ];

    if ($left === null) {
        // init_metrics($raw_data, null, null);
    }
    $sym_table = uprofiler_compute_flat_info($raw_data, $totals);

    if ($critical_path) {
        $children_table = uprofiler_get_children_table($raw_data);
        $node           = "main()";
        $path           = [ ];
        $path_edges     = [ ];
        $visited        = [ ];
        while ($node) {
            $visited[$node] = true;
            if (isset( $children_table[$node] )) {
                $max_child = null;
                foreach ($children_table[$node] as $child) {

                    if (isset( $visited[$child] )) {
                        continue;
                    }
                    if ($max_child === null ||
                        abs($raw_data[uprofiler_build_parent_child_key($node,
                            $child)]["wt"]) >
                        abs($raw_data[uprofiler_build_parent_child_key($node,
                            $max_child)]["wt"])
                    ) {
                        $max_child = $child;
                    }
                }
                if ($max_child !== null) {
                    $path[$max_child]                                                = true;
                    $path_edges[uprofiler_build_parent_child_key($node, $max_child)] = true;
                }
                $node = $max_child;
            } else {
                $node = null;
            }
        }
    }

    // if it is a benchmark callgraph, we make the benchmarked function the root.
    if ($source == "bm" && array_key_exists("main()", $sym_table)) {
        $total_times  = $sym_table["main()"]["ct"];
        $remove_funcs = [
            "main()",
            "hotprofiler_disable",
            "call_user_func_array",
            "uprofiler_disable"
        ];

        foreach ($remove_funcs as $cur_del_func) {
            if (array_key_exists($cur_del_func, $sym_table) &&
                $sym_table[$cur_del_func]["ct"] == $total_times
            ) {
                unset( $sym_table[$cur_del_func] );
            }
        }
    }

    // use the function to filter out irrelevant functions.
    if (! empty( $func )) {
        $interested_funcs = [ ];
        foreach ($raw_data as $parent_child => $info) {
            list( $parent, $child ) = uprofiler_parse_parent_child($parent_child);
            if ($parent == $func || $child == $func) {
                $interested_funcs[$parent] = 1;
                $interested_funcs[$child]  = 1;
            }
        }
        foreach ($sym_table as $symbol => $info) {
            if (! array_key_exists($symbol, $interested_funcs)) {
                unset( $sym_table[$symbol] );
            }
        }
    }

    $result = "digraph call_graph {\n";

    // Filter out functions whose exclusive time ratio is below threshold, and
    // also assign a unique integer id for each function to be generated. In the
    // meantime, find the function with the most exclusive time (potentially the
    // performance bottleneck).
    $cur_id = 0;
    $max_wt = 0;
    foreach ($sym_table as $symbol => $info) {
        if (empty( $func ) && abs($info["wt"] / $totals["wt"]) < $threshold) {
            unset( $sym_table[$symbol] );
            continue;
        }
        if ($max_wt == 0 || $max_wt < abs($info["excl_wt"])) {
            $max_wt = abs($info["excl_wt"]);
        }
        $sym_table[$symbol]["id"] = $cur_id;
        $cur_id ++;
    }

    // Generate all nodes' information.
    foreach ($sym_table as $symbol => $info) {
        if ($info["excl_wt"] == 0) {
            $sizing_factor = $max_sizing_ratio;
        } else {
            $sizing_factor = $max_wt / abs($info["excl_wt"]);
            if ($sizing_factor > $max_sizing_ratio) {
                $sizing_factor = $max_sizing_ratio;
            }
        }
        $fillcolor = ( ( $sizing_factor < 1.5 ) ?
            ", style=filled, fillcolor=red" : "" );

        if ($critical_path) {
            // highlight nodes along critical path.
            if (! $fillcolor && array_key_exists($symbol, $path)) {
                $fillcolor = ", style=filled, fillcolor=yellow";
            }
        }

        $fontsize = ", fontsize="
                    . (int) ( $max_fontsize / ( ( $sizing_factor - 1 ) / 10 + 1 ) );

        $width  = ", width=" . sprintf("%.1f", $max_width / $sizing_factor);
        $height = ", height=" . sprintf("%.1f", $max_height / $sizing_factor);

        if ($symbol == "main()") {
            $shape = "octagon";
            $name  = "Total: " . ( $totals["wt"] / 1000.0 ) . " ms\\n";
            $name .= addslashes(isset( $page ) ? $page : $symbol);
        } else {
            $shape = "box";
            $name  = addslashes($symbol) . "\\nInc: " . sprintf("%.3f", $info["wt"] / 1000) .
                     " ms (" . sprintf("%.1f%%", 100 * $info["wt"] / $totals["wt"]) . ")";
        }
        if ($left === null) {
            $label = ", label=\"" . $name . "\\nExcl: "
                     . ( sprintf("%.3f", $info["excl_wt"] / 1000.0) ) . " ms ("
                     . sprintf("%.1f%%", 100 * $info["excl_wt"] / $totals["wt"])
                     . ")\\n" . $info["ct"] . " total calls\"";
        } else {
            if (isset( $left[$symbol] ) && isset( $right[$symbol] )) {
                $label = ", label=\"" . addslashes($symbol) .
                         "\\nInc: " . ( sprintf("%.3f", $left[$symbol]["wt"] / 1000.0) )
                         . " ms - "
                         . ( sprintf("%.3f", $right[$symbol]["wt"] / 1000.0) ) . " ms = "
                         . ( sprintf("%.3f", $info["wt"] / 1000.0) ) . " ms" .
                         "\\nExcl: "
                         . ( sprintf("%.3f", $left[$symbol]["excl_wt"] / 1000.0) )
                         . " ms - " . ( sprintf("%.3f", $right[$symbol]["excl_wt"] / 1000.0) )
                         . " ms = " . ( sprintf("%.3f", $info["excl_wt"] / 1000.0) ) . " ms" .
                         "\\nCalls: " . ( sprintf("%.3f", $left[$symbol]["ct"]) ) . " - "
                         . ( sprintf("%.3f", $right[$symbol]["ct"]) ) . " = "
                         . ( sprintf("%.3f", $info["ct"]) ) . "\"";
            } else if (isset( $left[$symbol] )) {
                $label = ", label=\"" . addslashes($symbol) .
                         "\\nInc: " . ( sprintf("%.3f", $left[$symbol]["wt"] / 1000.0) )
                         . " ms - 0 ms = " . ( sprintf("%.3f", $info["wt"] / 1000.0) )
                         . " ms" . "\\nExcl: "
                         . ( sprintf("%.3f", $left[$symbol]["excl_wt"] / 1000.0) )
                         . " ms - 0 ms = "
                         . ( sprintf("%.3f", $info["excl_wt"] / 1000.0) ) . " ms" .
                         "\\nCalls: " . ( sprintf("%.3f", $left[$symbol]["ct"]) ) . " - 0 = "
                         . ( sprintf("%.3f", $info["ct"]) ) . "\"";
            } else {
                $label = ", label=\"" . addslashes($symbol) .
                         "\\nInc: 0 ms - "
                         . ( sprintf("%.3f", $right[$symbol]["wt"] / 1000.0) )
                         . " ms = " . ( sprintf("%.3f", $info["wt"] / 1000.0) ) . " ms" .
                         "\\nExcl: 0 ms - "
                         . ( sprintf("%.3f", $right[$symbol]["excl_wt"] / 1000.0) )
                         . " ms = " . ( sprintf("%.3f", $info["excl_wt"] / 1000.0) ) . " ms" .
                         "\\nCalls: 0 - " . ( sprintf("%.3f", $right[$symbol]["ct"]) )
                         . " = " . ( sprintf("%.3f", $info["ct"]) ) . "\"";
            }
        }
        $result .= "N" . $sym_table[$symbol]["id"];
        $result .= "[shape=$shape " . $label . $width
                   . $height . $fontsize . $fillcolor . "];\n";
    }

    // Generate all the edges' information.
    foreach ($raw_data as $parent_child => $info) {
        list( $parent, $child ) = uprofiler_parse_parent_child($parent_child);

        if (isset( $sym_table[$parent] ) && isset( $sym_table[$child] ) &&
            ( empty( $func ) ||
              ( ! empty( $func ) && ( $parent == $func || $child == $func ) ) )
        ) {

            $label = $info["ct"] == 1 ? $info["ct"] . " call" : $info["ct"] . " calls";

            $headlabel = $sym_table[$child]["wt"] > 0 ?
                sprintf("%.1f%%", 100 * $info["wt"]
                                  / $sym_table[$child]["wt"])
                : "0.0%";

            $taillabel = ( $sym_table[$parent]["wt"] > 0 ) ?
                sprintf("%.1f%%",
                    100 * $info["wt"] /
                    ( $sym_table[$parent]["wt"] - $sym_table["$parent"]["excl_wt"] ))
                : "0.0%";

            $linewidth  = 1;
            $arrow_size = 1;

            if ($critical_path &&
                isset( $path_edges[uprofiler_build_parent_child_key($parent, $child)] )
            ) {
                $linewidth  = 10;
                $arrow_size = 2;
            }

            $result .= "N" . $sym_table[$parent]["id"] . " -> N"
                       . $sym_table[$child]["id"];
            $result .= "[arrowsize=$arrow_size, color=grey, style=\"setlinewidth($linewidth)\","
                       . " label=\""
                       . $label . "\", headlabel=\"" . $headlabel
                       . "\", taillabel=\"" . $taillabel . "\" ]";
            $result .= ";\n";

        }
    }
    $result = $result . "\n}";

    return $result;
}

function  uprofiler_render_diff_image(
    $uprofiler_runs_impl,
    $run1,
    $run2,
    $type,
    $threshold,
    $source
) {
    $total1;
    $total2;

    $raw_data1 = $uprofiler_runs_impl->get_run($run1, $source, $desc_unused);
    $raw_data2 = $uprofiler_runs_impl->get_run($run2, $source, $desc_unused);

    // init_metrics($raw_data1, null, null);
    $children_table1 = uprofiler_get_children_table($raw_data1);
    $children_table2 = uprofiler_get_children_table($raw_data2);
    $symbol_tab1     = uprofiler_compute_flat_info($raw_data1, $total1);
    $symbol_tab2     = uprofiler_compute_flat_info($raw_data2, $total2);
    $run_delta       = uprofiler_compute_diff($raw_data1, $raw_data2);
    $script          = uprofiler_generate_dot_script($run_delta, $threshold, $source,
        null, null, true,
        $symbol_tab1, $symbol_tab2);
    $content         = uprofiler_generate_image_by_dot($script, $type);

    uprofiler_generate_mime_header($type, strlen($content));
    echo $content;
}

/**
 * Generate image content from phprof run id.
 *
 * @param object $uprofiler_runs_impl An object that implements
 *                                    the iUprofilerRuns interface
 * @param        run_id               , integer, the unique id for the phprof run, this is the
 *                                    primary key for phprof database table.
 * @param        type                 , string, one of the supported image types. See also
 *                                    $uprofiler_legal_image_types.
 * @param        threshold            , float, the threshold value [0,1). The functions in the
 *                                    raw_data whose exclusive wall times ratio are below the
 *                                    threshold will be filtered out and won't apprear in the
 *                                    generated image.
 * @param        func                 , string, the focus function.
 * @returns, string, the DOT script to generate image.
 *
 * @author cjiang
 */
function uprofiler_get_content_by_run(
    $uprofiler_runs_impl,
    $run_id,
    $type,
    $threshold,
    $func,
    $source,
    $critical_path
) {
    if (! $run_id) {
        return "";
    }

    $raw_data = $uprofiler_runs_impl->get_run($run_id, $source, $description);
    if (! $raw_data) {
        uprofiler_error("Raw data is empty");
        return "";
    }

    $script = uprofiler_generate_dot_script($raw_data, $threshold, $source,
        $description, $func, $critical_path);

    $content = uprofiler_generate_image_by_dot($script, $type);
    return $content;
}

/**
 * Generate image from phprof run id and send it to client.
 *
 * @param object $uprofiler_runs_impl An object that implements
 *                                    the iUprofilerRuns interface
 * @param        run_id               , integer, the unique id for the phprof run, this is the
 *                                    primary key for phprof database table.
 * @param        type                 , string, one of the supported image types. See also
 *                                    $uprofiler_legal_image_types.
 * @param        threshold            , float, the threshold value [0,1). The functions in the
 *                                    raw_data whose exclusive wall times ratio are below the
 *                                    threshold will be filtered out and won't appear in the
 *                                    generated image.
 * @param        func                 , string, the focus function.
 * @param        bool                 , does this run correspond to a PHProfLive run or a dev run?
 *
 * @author cjiang
 */
function uprofiler_render_image(
    $uprofiler_runs_impl,
    $run_id,
    $type,
    $threshold,
    $func,
    $source,
    $critical_path
) {

    $content = uprofiler_get_content_by_run($uprofiler_runs_impl, $run_id, $type,
        $threshold,
        $func, $source, $critical_path);
    if (! $content) {
        print "Error: either we can not find profile data for run_id " . $run_id
              . " or the threshold " . $threshold . " is too small or you do not"
              . " have 'dot' image generation utility installed.";
        exit();
    }

    uprofiler_generate_mime_header($type, strlen($content));
    echo $content;
}
