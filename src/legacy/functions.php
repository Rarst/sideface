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
        'wt'      => ['Wall', 'microsecs', 'walltime'],
        'ut'      => ['User', 'microsecs', 'user cpu time'],
        'st'      => ['Sys', 'microsecs', 'system cpu time'],
        'cpu'     => ['Cpu', 'microsecs', 'cpu time'],
        'mu'      => ['MUse', 'bytes', 'memory usage'],
        'pmu'     => ['PMUse', 'bytes', 'peak memory usage'],
        'samples' => ['Samples', 'samples', 'cpu time']
    ];
    return $possible_metrics;
}

/**
 * Takes a parent/child function name encoded as
 * "a==>b" and returns array("a", "b").
 *
 * @author Kannan
 */
function uprofiler_parse_parent_child($parent_child)
{
    $ret = explode('==>', $parent_child);

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
        return $parent . '==>' . $child;
    } else {
        return $child;
    }
}
