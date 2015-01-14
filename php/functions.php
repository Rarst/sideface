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
        error_log("uprofiler Error: both ==>main() and main() set in raw data...");
    }

    foreach ($raw_data as $parent_child => $info) {
        foreach ($info as $metric => $value) {
            $raw_data_total[$parent_child][$metric] = ( $value / $num_runs );
        }
    }

    return $raw_data_total;
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
            error_log("Error in Raw Data: parent & child are both: $parent");
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
