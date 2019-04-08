<?php

/**
 * Type definitions for URL params
 */
define( 'UPROFILER_STRING_PARAM', 1 );
define( 'UPROFILER_UINT_PARAM', 2 );
define( 'UPROFILER_FLOAT_PARAM', 3 );
define( 'UPROFILER_BOOL_PARAM', 4 );

// param name, its type, and default value
//$params = [
//    'run'       => [ UPROFILER_STRING_PARAM, '' ],
//    'wts'       => [ UPROFILER_STRING_PARAM, '' ],
//    'symbol'    => [ UPROFILER_STRING_PARAM, '' ],
//    'sort'      => [ UPROFILER_STRING_PARAM, 'wt' ], // wall time
//    'run1'      => [ UPROFILER_STRING_PARAM, '' ],
//    'run2'      => [ UPROFILER_STRING_PARAM, '' ],
//    'source'    => [ UPROFILER_STRING_PARAM, 'uprofiler' ],
//    'all'       => [ UPROFILER_UINT_PARAM, 0 ],
//];

// pull values of these params, and create named globals for each param
//uprofiler_param_init($params);

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

    $main_info = $raw_data['main()'];
    if (empty( $main_info )) {
        error_log('uprofiler: main() missing in raw data');
        return false;
    }

    // raw data should contain either wall time or samples information...
    if (isset( $main_info['wt'] )) {
        $prune_metric = 'wt';
    } elseif (isset( $main_info['samples'] )) {
        $prune_metric = 'samples';
    } else {
        error_log('uprofiler: for main() we must have either wt or samples attribute set');
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
                   ($parent != '__pruned__()') &&
                  ( $flat_info[$parent][$prune_metric] < $prune_threshold )
        ) {
            // Parent's overall inclusive metric is less than a threshold.
            // All edges to the parent node will get nuked, and this child will
            // be a dangling child.
            // So instead change its parent to be a special function __pruned__().
            $pruned_edge = uprofiler_build_parent_child_key('__pruned__()', $child);

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
        $link = '<a href="' . $href . '"';
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
    $desc = str_replace('<br>', ' ', stat_description($stat));

    print( "$desc: </td>" );
    print( number_format($val) );
    print(' (' . pct($val, $base) . '% of overall)');
    if (substr($stat, 0, 4) == 'excl') {
        $func_base = $symbol_info[str_replace('excl_', '', $stat)];
        print(' (' . pct($val, $func_base) . '% of this function)');
    }
    print('<br>');
}

/**
 * Computes percentage for a pair of values, and returns it
 * in string format.
 */
function pct($a, $b)
{
    if ($b == 0) {
        return 'N/A';
    } else {
        $res = (round($a * 1000 / $b) / 10 );
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

    return $res;
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
                error_log('Invalid param type passed to uprofiler_param_init: '
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
            error_log("$param is $val. It must be a valid boolean string.");
            return null;
    }

    return $val;

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

    error_log("$param is $val. It must be a float.");
    return null;
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

    error_log("$param is $val. It must be an unsigned integer.");
    return null;
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