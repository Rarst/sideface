<?php

/*
 * Prunes uprofiler raw data:
 *
 * Any node whose inclusive walltime accounts for less than $prune_percent
 * of total walltime is pruned. [It is possible that a child function isn't
 * pruned, but one or more of its parents get pruned. In such cases, when
 * viewing the child function's hierarchical information, the cost due to
 * the pruned parent(s) will be attributed to a special function/symbol
 * "__pruned__()".]
 *
 *  @param   array  $raw_data      uprofiler raw data to be pruned & validated.
 *  @param   double $prune_percent Any edges that account for less than
 *                                 $prune_percent of time will be pruned
 *                                 from the raw data.
 *
 *  @return  array  Returns the pruned raw data.
 *
 *  @author Kannan
 */
function uprofiler_prune_run($raw_data, $prune_percent)
{

    $main_info = $raw_data["main()"];
    if (empty( $main_info )) {
        error_log("uprofiler: main() missing in raw data");
        return false;
    }

    // raw data should contain either wall time or samples information...
    if (isset( $main_info["wt"] )) {
        $prune_metric = "wt";
    } elseif (isset( $main_info["samples"] )) {
        $prune_metric = "samples";
    } else {
        error_log("uprofiler: for main() we must have either wt or samples attribute set");
        return false;
    }

    // determine the metrics present in the raw data..
    $metrics = [ ];
    foreach ($main_info as $metric => $val) {
        if (isset( $val )) {
            $metrics[] = $metric;
        }
    }

    $prune_threshold = ( ( $main_info[$prune_metric] * $prune_percent ) / 100.0 );

    init_metrics($raw_data, null, null, false);
    $flat_info = uprofiler_compute_inclusive_times($raw_data);

    foreach ($raw_data as $parent_child => $info) {
        list( $parent, $child ) = uprofiler_parse_parent_child($parent_child);

        // is this child's overall total from all parents less than threshold?
        if ($flat_info[$child][$prune_metric] < $prune_threshold) {
            unset( $raw_data[$parent_child] ); // prune the edge
        } elseif ($parent &&
                   ( $parent != "__pruned__()" ) &&
                   ( $flat_info[$parent][$prune_metric] < $prune_threshold )
        ) {
            // Parent's overall inclusive metric is less than a threshold.
            // All edges to the parent node will get nuked, and this child will
            // be a dangling child.
            // So instead change its parent to be a special function __pruned__().
            $pruned_edge = uprofiler_build_parent_child_key("__pruned__()", $child);

            if (isset( $raw_data[$pruned_edge] )) {
                foreach ($metrics as $metric) {
                    $raw_data[$pruned_edge][$metric] += $raw_data[$parent_child][$metric];
                }
            } else {
                $raw_data[$pruned_edge] = $raw_data[$parent_child];
            }

            unset( $raw_data[$parent_child] ); // prune the edge
        }
    }

    return $raw_data;
}

/**
 * @param string $content the text/image/innerhtml/whatever for the link
 * @param string $href
 *
 * @return string
 */
function uprofiler_render_link($content, $href)
{
    if (! $content) {
        return '';
    }

    if ($href) {
        $link = '<a href="' . ( $href ) . '"';
    } else {
        $link = '<span';
    }

    $link .= '>';
    $link .= $content;
    if ($href) {
        $link .= '</a>';
    } else {
        $link .= '</span>';
    }

    return $link;
}

function print_source_link($info)
{
    if (strncmp($info['fn'], 'run_init', 8) && $info['fn'] !== 'main()') {
        if (defined('UPROFILER_SYMBOL_LOOKUP_URL')) {
            $link = uprofiler_render_link(
                'source',
                UPROFILER_SYMBOL_LOOKUP_URL . '?symbol=' . rawurlencode($info['fn'])
            );
            print( ' (' . $link . ')' );
        }
    }
}

function print_symbol_summary($symbol_info, $stat, $base)
{

    $val  = $symbol_info[$stat];
    $desc = str_replace("<br>", " ", stat_description($stat));

    print( "$desc: </td>" );
    print( number_format($val) );
    print( " (" . pct($val, $base) . "% of overall)" );
    if (substr($stat, 0, 4) == "excl") {
        $func_base = $symbol_info[str_replace("excl_", "", $stat)];
        print( " (" . pct($val, $func_base) . "% of this function)" );
    }
    print( "<br>" );
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