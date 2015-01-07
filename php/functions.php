<?php

/**
 * Generate references to required stylesheets & javascript.
 *
 * If the calling script (such as index.php) resides in
 * a different location that than 'uprofiler_html' directory the
 * caller must provide the URL path to 'uprofiler_html' directory
 * so that the correct location of the style sheets/javascript
 * can be specified in the generated HTML.
 *
 * @param string $ui_dir_url_path
 */
function uprofiler_include_js_css($ui_dir_url_path = '')
{
    if (empty( $ui_dir_url_path )) {
        $ui_dir_url_path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    }

    // style sheets
    echo "<link href='$ui_dir_url_path/css/uprofiler.css' rel='stylesheet' " .
         " type='text/css' />";
    echo "<link href='$ui_dir_url_path/jquery/jquery.tooltip.css' " .
         " rel='stylesheet' type='text/css' />";
    echo "<link href='$ui_dir_url_path/jquery/jquery.autocomplete.css' " .
         " rel='stylesheet' type='text/css' />";

    // javascript
    echo "<script src='$ui_dir_url_path/jquery/jquery-1.2.6.js'>" .
         "</script>";
    echo "<script src='$ui_dir_url_path/jquery/jquery.tooltip.js'>" .
         "</script>";
    echo "<script src='$ui_dir_url_path/jquery/jquery.autocomplete.js'>"
         . "</script>";
    echo "<script src='$ui_dir_url_path/js/uprofiler_report.js'></script>";
}

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
 * Implodes the text for a bunch of actions (such as links, forms,
 * into a HTML list and returns the text.
 *
 * @param array $actions
 *
 * @return string
 */
function uprofiler_render_actions($actions)
{
    $out = [ ];

    if (count($actions)) {
        $out[] = '<ul class="uprofiler_actions">';
        foreach ($actions as $action) {
            $out[] = '<li>' . $action . '</li>';
        }
        $out[] = '</ul>';
    }

    return implode('', $out);
}


/**
 * @param string $content the text/image/innerhtml/whatever for the link
 * @param string $href
 * @param string $class
 * @param string $id
 * @param string $title
 * @param string $target
 * @param string $onclick
 * @param string $style
 * @param string $access
 * @param string $onmouseover
 * @param string $onmouseout
 * @param string $onmousedown
 *
 * @return string
 */
function uprofiler_render_link(
    $content,
    $href,
    $class = '',
    $id = '',
    $title = '',
    $target = '',
    $onclick = '',
    $style = '',
    $access = '',
    $onmouseover = '',
    $onmouseout = '',
    $onmousedown = ''
) {

    if (! $content) {
        return '';
    }

    if ($href) {
        $link = '<a href="' . ( $href ) . '"';
    } else {
        $link = '<span';
    }

    if ($class) {
        $link .= ' class="' . ( $class ) . '"';
    }
    if ($id) {
        $link .= ' id="' . ( $id ) . '"';
    }
    if ($title) {
        $link .= ' title="' . ( $title ) . '"';
    }
    if ($target) {
        $link .= ' target="' . ( $target ) . '"';
    }
    if ($onclick && $href) {
        $link .= ' onclick="' . ( $onclick ) . '"';
    }
    if ($style && $href) {
        $link .= ' style="' . ( $style ) . '"';
    }
    if ($access && $href) {
        $link .= ' accesskey="' . ( $access ) . '"';
    }
    if ($onmouseover) {
        $link .= ' onmouseover="' . ( $onmouseover ) . '"';
    }
    if ($onmouseout) {
        $link .= ' onmouseout="' . ( $onmouseout ) . '"';
    }
    if ($onmousedown) {
        $link .= ' onmousedown="' . ( $onmousedown ) . '"';
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
 * Analyze raw data & generate the profiler report
 * (common for both single run mode and diff mode).
 *
 * @author: Kannan
 */
function profiler_report(
    $url_params,
    $rep_symbol,
    $sort,
    $run1,
    $run1_desc,
    $run1_data,
    $run2 = 0,
    $run2_desc = "",
    $run2_data = [ ]
) {
    global $totals;
    global $totals_1;
    global $totals_2;
    global $stats;
    global $pc_stats;
    global $diff_mode;
    global $base_path;

    // if we are reporting on a specific function, we can trim down
    // the report(s) to just stuff that is relevant to this function.
    // That way compute_flat_info()/compute_diff() etc. do not have
    // to needlessly work hard on churning irrelevant data.
    if (! empty( $rep_symbol )) {
        $run1_data = uprofiler_trim_run($run1_data, [ $rep_symbol ]);
        if ($diff_mode) {
            $run2_data = uprofiler_trim_run($run2_data, [ $rep_symbol ]);
        }
    }

    if ($diff_mode) {
        $run_delta   = uprofiler_compute_diff($run1_data, $run2_data);
        $symbol_tab  = uprofiler_compute_flat_info($run_delta, $totals);
        $symbol_tab1 = uprofiler_compute_flat_info($run1_data, $totals_1);
        $symbol_tab2 = uprofiler_compute_flat_info($run2_data, $totals_2);
    } else {
        $symbol_tab = uprofiler_compute_flat_info($run1_data, $totals);
    }

    $run1_txt = sprintf("<b>Run #%s:</b> %s",
        $run1, $run1_desc);

    $base_url_params = uprofiler_array_unset(uprofiler_array_unset($url_params,
        'symbol'),
        'all');

    $top_link_query_string = "$base_path/?" . http_build_query($base_url_params);

    if ($diff_mode) {
        $diff_text       = "Diff";
        $base_url_params = uprofiler_array_unset($base_url_params, 'run1');
        $base_url_params = uprofiler_array_unset($base_url_params, 'run2');
        $run1_link       = uprofiler_render_link('View Run #' . $run1,
            "$base_path/?" .
            http_build_query(uprofiler_array_set($base_url_params,
                'run',
                $run1)));
        $run2_txt        = sprintf("<b>Run #%s:</b> %s",
            $run2, $run2_desc);

        $run2_link = uprofiler_render_link('View Run #' . $run2,
            "$base_path/?" .
            http_build_query(uprofiler_array_set($base_url_params,
                'run',
                $run2)));
    } else {
        $diff_text = "Run";
    }

    // set up the action links for operations that can be done on this report
    $links    = [ ];
    $links [] = uprofiler_render_link("View Top Level $diff_text Report",
        $top_link_query_string);

    if ($diff_mode) {
        $inverted_params         = $url_params;
        $inverted_params['run1'] = $url_params['run2'];
        $inverted_params['run2'] = $url_params['run1'];

        // view the different runs or invert the current diff
        $links [] = $run1_link;
        $links [] = $run2_link;
        $links [] = uprofiler_render_link('Invert ' . $diff_text . ' Report',
            "$base_path/?" .
            http_build_query($inverted_params));
    }

    // lookup function typeahead form
    $links [] = '<input class="function_typeahead" ' .
                ' type="input" size="40" maxlength="100" />';

    echo uprofiler_render_actions($links);


    echo
        '<dl class=phprof_report_info>' .
        '  <dt>' . $diff_text . ' Report</dt>' .
        '  <dd>' . ( $diff_mode ?
            $run1_txt . '<br><b>vs.</b><br>' . $run2_txt :
            $run1_txt ) .
        '  </dd>' .
        '  <dt>Tip</dt>' .
        '  <dd>Click a function name below to drill down.</dd>' .
        '</dl>' .
        '<div style="clear: both; margin: 3em 0em;"></div>';

    // data tables
    if (! empty( $rep_symbol )) {
        if (! isset( $symbol_tab[$rep_symbol] )) {
            echo "<hr>Symbol <b>$rep_symbol</b> not found in uprofiler run</b><hr>";
            return;
        }

        /* single function report with parent/child information */
        if ($diff_mode) {
            $info1 = isset( $symbol_tab1[$rep_symbol] ) ?
                $symbol_tab1[$rep_symbol] : null;
            $info2 = isset( $symbol_tab2[$rep_symbol] ) ?
                $symbol_tab2[$rep_symbol] : null;
            symbol_report($url_params, $run_delta, $symbol_tab[$rep_symbol],
                $sort, $rep_symbol,
                $run1, $info1,
                $run2, $info2);
        } else {
            symbol_report($url_params, $run1_data, $symbol_tab[$rep_symbol],
                $sort, $rep_symbol, $run1);
        }
    } else {
        /* flat top-level report of all functions */
        full_report($url_params, $symbol_tab, $sort, $run1, $run2);
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
 * Prints a <td> element with a numeric value.
 */
function print_td_num($num, $fmt_func, $bold = false, $attributes = null)
{

    $class = get_print_class($num, $bold);

    if (! empty( $fmt_func ) && is_numeric($num)) {
        $num = call_user_func($fmt_func, $num);
    }

    print( "<td $attributes $class>$num</td>\n" );
}

/**
 * Prints a <td> element with a percentage.
 */
function print_td_pct($number, $denom, $bold = false, $attributes = null)
{
    global $vbar;
    global $vbbar;
    global $diff_mode;

    $class = get_print_class($number, $bold);

    if ($denom == 0) {
        $pct = "N/A%";
    } else {
        $pct = uprofiler_percent_format($number / abs($denom));
    }

    print( "<td $attributes $class>$pct</td>\n" );
}

/**
 * Print "flat" data corresponding to one function.
 *
 * @author Kannan
 */
function print_function_info($url_params, $info, $sort, $run1, $run2)
{
    static $odd_even = 0;

    global $totals;
    global $sort_col;
    global $metrics;
    global $format_cbk;
    global $display_calls;
    global $base_path;

    // Toggle $odd_or_even
    $odd_even = 1 - $odd_even;

    if ($odd_even) {
        print( "<tr>" );
    } else {
        print( '<tr bgcolor="#e5e5e5">' );
    }

    $href = "$base_path/?" .
            http_build_query(uprofiler_array_set($url_params,
                'symbol', $info["fn"]));

    print( '<td>' );
    print( uprofiler_render_link($info["fn"], $href) );
    print_source_link($info);
    print( "</td>\n" );

    if ($display_calls) {
        // Call Count..
        print_td_num($info["ct"], $format_cbk["ct"], ( $sort_col == "ct" ));
        print_td_pct($info["ct"], $totals["ct"], ( $sort_col == "ct" ));
    }

    // Other metrics..
    foreach ($metrics as $metric) {
        // Inclusive metric
        print_td_num($info[$metric], $format_cbk[$metric],
            ( $sort_col == $metric ));
        print_td_pct($info[$metric], $totals[$metric],
            ( $sort_col == $metric ));

        // Exclusive Metric
        print_td_num($info["excl_" . $metric],
            $format_cbk["excl_" . $metric],
            ( $sort_col == "excl_" . $metric ));
        print_td_pct($info["excl_" . $metric],
            $totals[$metric],
            ( $sort_col == "excl_" . $metric ));
    }

    print( "</tr>\n" );
}

/**
 * Print non-hierarchical (flat-view) of profiler data.
 *
 * @author Kannan
 */
function print_flat_data($url_params, $title, $flat_data, $sort, $run1, $run2, $limit)
{

    global $stats;
    global $sortable_columns;
    global $vwbar;
    global $base_path;

    $size = count($flat_data);
    if (! $limit) {              // no limit
        $limit        = $size;
        $display_link = "";
    } else {
        $display_link = uprofiler_render_link(" [ <b class=bubble>display all </b>]",
            "$base_path/?" .
            http_build_query(uprofiler_array_set($url_params,
                'all', 1)));
    }

    print( "<h3 align=center>$title $display_link</h3><br>" );

    print( '<table border=1 cellpadding=2 cellspacing=1 width="90%" '
           . 'rules=rows bordercolor="#bdc7d8" align=center>' );
    print( '<tr bgcolor="#bdc7d8" align=right>' );

    foreach ($stats as $stat) {
        $desc = stat_description($stat);
        if (array_key_exists($stat, $sortable_columns)) {
            $href   = "$base_path/?"
                      . http_build_query(uprofiler_array_set($url_params, 'sort', $stat));
            $header = uprofiler_render_link($desc, $href);
        } else {
            $header = $desc;
        }

        if ($stat == "fn") {
            print( "<th align=left><nobr>$header</th>" );
        } else {
            print( "<th " . $vwbar . "><nobr>$header</th>" );
        }
    }
    print( "</tr>\n" );

    if ($limit >= 0) {
        $limit = min($size, $limit);
        for ($i = 0; $i < $limit; $i ++) {
            print_function_info($url_params, $flat_data[$i], $sort, $run1, $run2);
        }
    } else {
        // if $limit is negative, print abs($limit) items starting from the end
        $limit = min($size, abs($limit));
        for ($i = 0; $i < $limit; $i ++) {
            print_function_info($url_params, $flat_data[$size - $i - 1], $sort, $run1, $run2);
        }
    }
    print( "</table>" );

    // let's print the display all link at the bottom as well...
    if ($display_link) {
        echo '<div style="text-align: left; padding: 2em">' . $display_link . '</div>';
    }

}

/**
 * Generates a tabular report for all functions. This is the top-level report.
 *
 * @author Kannan
 */
function full_report($url_params, $symbol_tab, $sort, $run1, $run2)
{
    global $vwbar;
    global $vbar;
    global $totals;
    global $totals_1;
    global $totals_2;
    global $metrics;
    global $diff_mode;
    global $descriptions;
    global $sort_col;
    global $format_cbk;
    global $display_calls;
    global $base_path;

    $possible_metrics = uprofiler_get_possible_metrics();

    if ($diff_mode) {

        $base_url_params = uprofiler_array_unset(uprofiler_array_unset($url_params,
            'run1'),
            'run2');
        $href1           = "$base_path/?" .
                           http_build_query(uprofiler_array_set($base_url_params,
                               'run', $run1));
        $href2           = "$base_path/?" .
                           http_build_query(uprofiler_array_set($base_url_params,
                               'run', $run2));

        print( "<h3><center>Overall Diff Summary</center></h3>" );
        print( '<table border=1 cellpadding=2 cellspacing=1 width="30%" '
               . 'rules=rows bordercolor="#bdc7d8" align=center>' . "\n" );
        print( '<tr bgcolor="#bdc7d8" align=right>' );
        print( "<th></th>" );
        print( "<th $vwbar>" . uprofiler_render_link("Run #$run1", $href1) . "</th>" );
        print( "<th $vwbar>" . uprofiler_render_link("Run #$run2", $href2) . "</th>" );
        print( "<th $vwbar>Diff</th>" );
        print( "<th $vwbar>Diff%</th>" );
        print( '</tr>' );

        if ($display_calls) {
            print( '<tr>' );
            print( "<td>Number of Function Calls</td>" );
            print_td_num($totals_1["ct"], $format_cbk["ct"]);
            print_td_num($totals_2["ct"], $format_cbk["ct"]);
            print_td_num($totals_2["ct"] - $totals_1["ct"], $format_cbk["ct"], true);
            print_td_pct($totals_2["ct"] - $totals_1["ct"], $totals_1["ct"], true);
            print( '</tr>' );
        }

        foreach ($metrics as $metric) {
            $m = $metric;
            print( '<tr>' );
            print( "<td>" . str_replace("<br>", " ", $descriptions[$m]) . "</td>" );
            print_td_num($totals_1[$m], $format_cbk[$m]);
            print_td_num($totals_2[$m], $format_cbk[$m]);
            print_td_num($totals_2[$m] - $totals_1[$m], $format_cbk[$m], true);
            print_td_pct($totals_2[$m] - $totals_1[$m], $totals_1[$m], true);
            print( '<tr>' );
        }
        print( '</table>' );

        $callgraph_report_title = '[View Regressions/Improvements using Callgraph Diff]';

    } else {
        print( "<p><center>\n" );

        print( '<table cellpadding=2 cellspacing=1 width="30%" '
               . 'bgcolor="#bdc7d8" align=center>' . "\n" );
        echo "<tr>";
        echo "<th style='text-align:right'>Overall Summary</th>";
        echo "<th></th>";
        echo "</tr>";

        foreach ($metrics as $metric) {
            echo "<tr>";
            echo "<td style='text-align:right; font-weight:bold'>Total "
                 . str_replace("<br>", " ", stat_description($metric)) . ":</td>";
            echo "<td>" . number_format($totals[$metric]) . " "
                 . $possible_metrics[$metric][1] . "</td>";
            echo "</tr>";
        }

        if ($display_calls) {
            echo "<tr>";
            echo "<td style='text-align:right; font-weight:bold'>Number of Function Calls:</td>";
            echo "<td>" . number_format($totals['ct']) . "</td>";
            echo "</tr>";
        }

        echo "</table>";
        print( "</center></p>\n" );

        $callgraph_report_title = '[View Full Callgraph]';
    }

    print( "<center><br><h3>" .
           uprofiler_render_link($callgraph_report_title,
               "$base_path/callgraph.php" . "?" . http_build_query($url_params))
           . "</h3></center>" );


    $flat_data = [ ];
    foreach ($symbol_tab as $symbol => $info) {
        $tmp         = $info;
        $tmp["fn"]   = $symbol;
        $flat_data[] = $tmp;
    }
    usort($flat_data, 'sort_cbk');

    print( "<br>" );

    if (! empty( $url_params['all'] )) {
        $all   = true;
        $limit = 0;    // display all rows
    } else {
        $all   = false;
        $limit = 100;  // display only limited number of rows
    }

    $desc = str_replace("<br>", " ", $descriptions[$sort_col]);

    if ($diff_mode) {
        if ($all) {
            $title = "Total Diff Report: '
               .'Sorted by absolute value of regression/improvement in $desc";
        } else {
            $title = "Top 100 <i style='color:red'>Regressions</i>/"
                     . "<i style='color:green'>Improvements</i>: "
                     . "Sorted by $desc Diff";
        }
    } else {
        if ($all) {
            $title = "Sorted by $desc";
        } else {
            $title = "Displaying top $limit functions: Sorted by $desc";
        }
    }
    print_flat_data($url_params, $title, $flat_data, $sort, $run1, $run2, $limit);
}


/**
 * Return attribute names and values to be used by javascript tooltip.
 */
function get_tooltip_attributes($type, $metric)
{
    return "type='$type' metric='$metric'";
}

/**
 * Print info for a parent or child function in the
 * parent & children report.
 *
 * @author Kannan
 */
function pc_info($info, $base_ct, $base_info, $parent)
{
    global $sort_col;
    global $metrics;
    global $format_cbk;
    global $display_calls;

    if ($parent) {
        $type = "Parent";
    } else {
        $type = "Child";
    }

    if ($display_calls) {
        $mouseoverct = get_tooltip_attributes($type, "ct");
        /* call count */
        print_td_num($info["ct"], $format_cbk["ct"], ( $sort_col == "ct" ), $mouseoverct);
        print_td_pct($info["ct"], $base_ct, ( $sort_col == "ct" ), $mouseoverct);
    }

    /* Inclusive metric values  */
    foreach ($metrics as $metric) {
        print_td_num($info[$metric], $format_cbk[$metric],
            ( $sort_col == $metric ),
            get_tooltip_attributes($type, $metric));
        print_td_pct($info[$metric], $base_info[$metric], ( $sort_col == $metric ),
            get_tooltip_attributes($type, $metric));
    }
}

function print_pc_array(
    $url_params,
    $results,
    $base_ct,
    $base_info,
    $parent,
    $run1,
    $run2
) {
    global $base_path;

    // Construct section title
    if ($parent) {
        $title = 'Parent function';
    } else {
        $title = 'Child function';
    }
    if (count($results) > 1) {
        $title .= 's';
    }

    print( "<tr bgcolor='#e0e0ff'><td>" );
    print( "<b><i><center>" . $title . "</center></i></b>" );
    print( "</td></tr>" );

    $odd_even = 0;
    foreach ($results as $info) {
        $href = "$base_path/?" .
                http_build_query(uprofiler_array_set($url_params,
                    'symbol', $info["fn"]));

        $odd_even = 1 - $odd_even;

        if ($odd_even) {
            print( '<tr>' );
        } else {
            print( '<tr bgcolor="#e5e5e5">' );
        }

        print( "<td>" . uprofiler_render_link($info["fn"], $href) );
        print_source_link($info);
        print( "</td>" );
        pc_info($info, $base_ct, $base_info, $parent);
        print( "</tr>" );
    }
}

function print_source_link($info)
{
    if (strncmp($info['fn'], 'run_init', 8) && $info['fn'] !== 'main()') {
        if (defined('UPROFILER_SYMBOL_LOOKUP_URL')) {
            $link = uprofiler_render_link(
                'source',
                UPROFILER_SYMBOL_LOOKUP_URL . '?symbol=' . rawurlencode($info["fn"]));
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
 * Generates a report for a single function/symbol.
 *
 * @author Kannan
 */
function symbol_report(
    $url_params,
    $run_data,
    $symbol_info,
    $sort,
    $rep_symbol,
    $run1,
    $symbol_info1 = null,
    $run2 = 0,
    $symbol_info2 = null
) {
    global $vwbar;
    global $vbar;
    global $totals;
    global $pc_stats;
    global $sortable_columns;
    global $metrics;
    global $diff_mode;
    global $descriptions;
    global $format_cbk;
    global $sort_col;
    global $display_calls;
    global $base_path;

    $possible_metrics = uprofiler_get_possible_metrics();

    if ($diff_mode) {
        $diff_text = "<b>Diff</b>";
        $regr_impr = "<i style='color:red'>Regression</i>/<i style='color:green'>Improvement</i>";
    } else {
        $diff_text = "";
        $regr_impr = "";
    }

    if ($diff_mode) {

        $base_url_params = uprofiler_array_unset(uprofiler_array_unset($url_params,
            'run1'),
            'run2');
        $href1           = "$base_path?"
                           . http_build_query(uprofiler_array_set($base_url_params, 'run', $run1));
        $href2           = "$base_path?"
                           . http_build_query(uprofiler_array_set($base_url_params, 'run', $run2));

        print( "<h3 align=center>$regr_impr summary for $rep_symbol<br><br></h3>" );
        print( '<table border=1 cellpadding=2 cellspacing=1 width="30%" '
               . 'rules=rows bordercolor="#bdc7d8" align=center>' . "\n" );
        print( '<tr bgcolor="#bdc7d8" align=right>' );
        print( "<th align=left>$rep_symbol</th>" );
        print( "<th $vwbar><a href=" . $href1 . ">Run #$run1</a></th>" );
        print( "<th $vwbar><a href=" . $href2 . ">Run #$run2</a></th>" );
        print( "<th $vwbar>Diff</th>" );
        print( "<th $vwbar>Diff%</th>" );
        print( '</tr>' );
        print( '<tr>' );

        if ($display_calls) {
            print( "<td>Number of Function Calls</td>" );
            print_td_num($symbol_info1["ct"], $format_cbk["ct"]);
            print_td_num($symbol_info2["ct"], $format_cbk["ct"]);
            print_td_num($symbol_info2["ct"] - $symbol_info1["ct"],
                $format_cbk["ct"], true);
            print_td_pct($symbol_info2["ct"] - $symbol_info1["ct"],
                $symbol_info1["ct"], true);
            print( '</tr>' );
        }


        foreach ($metrics as $metric) {
            $m = $metric;

            // Inclusive stat for metric
            print( '<tr>' );
            print( "<td>" . str_replace("<br>", " ", $descriptions[$m]) . "</td>" );
            print_td_num($symbol_info1[$m], $format_cbk[$m]);
            print_td_num($symbol_info2[$m], $format_cbk[$m]);
            print_td_num($symbol_info2[$m] - $symbol_info1[$m], $format_cbk[$m], true);
            print_td_pct($symbol_info2[$m] - $symbol_info1[$m], $symbol_info1[$m], true);
            print( '</tr>' );

            // AVG (per call) Inclusive stat for metric
            print( '<tr>' );
            print( "<td>" . str_replace("<br>", " ", $descriptions[$m]) . " per call </td>" );
            $avg_info1 = 'N/A';
            $avg_info2 = 'N/A';
            if ($symbol_info1['ct'] > 0) {
                $avg_info1 = ( $symbol_info1[$m] / $symbol_info1['ct'] );
            }
            if ($symbol_info2['ct'] > 0) {
                $avg_info2 = ( $symbol_info2[$m] / $symbol_info2['ct'] );
            }
            print_td_num($avg_info1, $format_cbk[$m]);
            print_td_num($avg_info2, $format_cbk[$m]);
            print_td_num($avg_info2 - $avg_info1, $format_cbk[$m], true);
            print_td_pct($avg_info2 - $avg_info1, $avg_info1, true);
            print( '</tr>' );

            // Exclusive stat for metric
            $m = "excl_" . $metric;
            print( '<tr style="border-bottom: 1px solid black;">' );
            print( "<td>" . str_replace("<br>", " ", $descriptions[$m]) . "</td>" );
            print_td_num($symbol_info1[$m], $format_cbk[$m]);
            print_td_num($symbol_info2[$m], $format_cbk[$m]);
            print_td_num($symbol_info2[$m] - $symbol_info1[$m], $format_cbk[$m], true);
            print_td_pct($symbol_info2[$m] - $symbol_info1[$m], $symbol_info1[$m], true);
            print( '</tr>' );
        }

        print( '</table>' );
    }

    print( "<br><h4><center>" );
    print( "Parent/Child $regr_impr report for <b>$rep_symbol</b>" );

    $callgraph_href = "$base_path/callgraph.php?"
                      . http_build_query(uprofiler_array_set($url_params, 'func', $rep_symbol));

    print( " <a href='$callgraph_href'>[View Callgraph $diff_text]</a><br>" );

    print( "</center></h4><br>" );

    print( '<table border=1 cellpadding=2 cellspacing=1 width="90%" '
           . 'rules=rows bordercolor="#bdc7d8" align=center>' . "\n" );
    print( '<tr bgcolor="#bdc7d8" align=right>' );

    foreach ($pc_stats as $stat) {
        $desc = stat_description($stat);
        if (array_key_exists($stat, $sortable_columns)) {

            $href   = "$base_path/?" .
                      http_build_query(uprofiler_array_set($url_params,
                          'sort', $stat));
            $header = uprofiler_render_link($desc, $href);
        } else {
            $header = $desc;
        }

        if ($stat == "fn") {
            print( "<th align=left><nobr>$header</th>" );
        } else {
            print( "<th " . $vwbar . "><nobr>$header</th>" );
        }
    }
    print( "</tr>" );

    print( "<tr bgcolor='#e0e0ff'><td>" );
    print( "<b><i><center>Current Function</center></i></b>" );
    print( "</td></tr>" );

    print( "<tr>" );
    // make this a self-reference to facilitate copy-pasting snippets to e-mails
    print( "<td><a href=''>$rep_symbol</a>" );
    print_source_link([ 'fn' => $rep_symbol ]);
    print( "</td>" );

    if ($display_calls) {
        // Call Count
        print_td_num($symbol_info["ct"], $format_cbk["ct"]);
        print_td_pct($symbol_info["ct"], $totals["ct"]);
    }

    // Inclusive Metrics for current function
    foreach ($metrics as $metric) {
        print_td_num($symbol_info[$metric], $format_cbk[$metric], ( $sort_col == $metric ));
        print_td_pct($symbol_info[$metric], $totals[$metric], ( $sort_col == $metric ));
    }
    print( "</tr>" );

    print( "<tr bgcolor='#ffffff'>" );
    print( "<td style='text-align:right;color:blue'>"
           . "Exclusive Metrics $diff_text for Current Function</td>" );

    if ($display_calls) {
        // Call Count
        print( "<td $vbar></td>" );
        print( "<td $vbar></td>" );
    }

    // Exclusive Metrics for current function
    foreach ($metrics as $metric) {
        print_td_num($symbol_info["excl_" . $metric], $format_cbk["excl_" . $metric],
            ( $sort_col == $metric ),
            get_tooltip_attributes("Child", $metric));
        print_td_pct($symbol_info["excl_" . $metric], $symbol_info[$metric],
            ( $sort_col == $metric ),
            get_tooltip_attributes("Child", $metric));
    }
    print( "</tr>" );

    // list of callers/parent functions
    $results = [ ];
    if ($display_calls) {
        $base_ct = $symbol_info["ct"];
    } else {
        $base_ct = 0;
    }
    foreach ($metrics as $metric) {
        $base_info[$metric] = $symbol_info[$metric];
    }
    foreach ($run_data as $parent_child => $info) {
        list( $parent, $child ) = uprofiler_parse_parent_child($parent_child);
        if (( $child == $rep_symbol ) && ( $parent )) {
            $info_tmp       = $info;
            $info_tmp["fn"] = $parent;
            $results[]      = $info_tmp;
        }
    }
    usort($results, 'sort_cbk');

    if (count($results) > 0) {
        print_pc_array($url_params, $results, $base_ct, $base_info, true,
            $run1, $run2);
    }

    // list of callees/child functions
    $results = [ ];
    $base_ct = 0;
    foreach ($run_data as $parent_child => $info) {
        list( $parent, $child ) = uprofiler_parse_parent_child($parent_child);
        if ($parent == $rep_symbol) {
            $info_tmp       = $info;
            $info_tmp["fn"] = $child;
            $results[]      = $info_tmp;
            if ($display_calls) {
                $base_ct += $info["ct"];
            }
        }
    }
    usort($results, 'sort_cbk');

    if (count($results)) {
        print_pc_array($url_params, $results, $base_ct, $base_info, false,
            $run1, $run2);
    }

    print( "</table>" );

    // These will be used for pop-up tips/help.
    // Related javascript code is in: uprofiler_report.js
    print( "\n" );
    print( '<script language="javascript">' . "\n" );
    print( "var func_name = '\"" . $rep_symbol . "\"';\n" );
    print( "var total_child_ct  = " . $base_ct . ";\n" );
    if ($display_calls) {
        print( "var func_ct   = " . $symbol_info["ct"] . ";\n" );
    }
    print( "var func_metrics = new Array();\n" );
    print( "var metrics_col  = new Array();\n" );
    print( "var metrics_desc  = new Array();\n" );
    if ($diff_mode) {
        print( "var diff_mode = true;\n" );
    } else {
        print( "var diff_mode = false;\n" );
    }
    $column_index = 3; // First three columns are Func Name, Calls, Calls%
    foreach ($metrics as $metric) {
        print( "func_metrics[\"" . $metric . "\"] = " . round($symbol_info[$metric]) . ";\n" );
        print( "metrics_col[\"" . $metric . "\"] = " . $column_index . ";\n" );
        print( "metrics_desc[\"" . $metric . "\"] = \"" . $possible_metrics[$metric][2] . "\";\n" );

        // each metric has two columns..
        $column_index += 2;
    }
    print( '</script>' );
    print( "\n" );

}

/**
 * Generate the profiler report for a single run.
 *
 * @author Kannan
 */
function profiler_single_run_report(
    $url_params,
    $uprofiler_data,
    $run_desc,
    $rep_symbol,
    $sort,
    $run
) {

    init_metrics($uprofiler_data, $rep_symbol, $sort, false);

    profiler_report($url_params, $rep_symbol, $sort, $run, $run_desc,
        $uprofiler_data);
}


/**
 * Generate the profiler report for diff mode (delta between two runs).
 *
 * @author Kannan
 */
function profiler_diff_report(
    $url_params,
    $uprofiler_data1,
    $run1_desc,
    $uprofiler_data2,
    $run2_desc,
    $rep_symbol,
    $sort,
    $run1,
    $run2
) {


    // Initialize what metrics we'll display based on data in Run2
    init_metrics($uprofiler_data2, $rep_symbol, $sort, true);

    profiler_report($url_params,
        $rep_symbol,
        $sort,
        $run1,
        $run1_desc,
        $uprofiler_data1,
        $run2,
        $run2_desc,
        $uprofiler_data2);
}


/**
 * Generate a uprofiler Display View given the various URL parameters
 * as arguments. The first argument is an object that implements
 * the iUprofilerRuns interface.
 *
 * @param object $uprofiler_runs_impl An object that implements
 *                                    the iUprofilerRuns interface
 *                                    .
 * @param array  $url_params          Array of non-default URL params.
 *
 * @param string $source              Category/type of the run. The source in
 *                                    combination with the run id uniquely
 *                                    determines a profiler run.
 *
 * @param string $run                 run id, or comma separated sequence of
 *                                    run ids. The latter is used if an aggregate
 *                                    report of the runs is desired.
 *
 * @param string $wts                 Comma separate list of integers.
 *                                    Represents the weighted ratio in
 *                                    which which a set of runs will be
 *                                    aggregated. [Used only for aggregate
 *                                    reports.]
 *
 * @param string $symbol              Function symbol. If non-empty then the
 *                                    parent/child view of this function is
 *                                    displayed. If empty, a flat-profile view
 *                                    of the functions is displayed.
 *
 * @param string $run1                Base run id (for diff reports)
 *
 * @param string $run2                New run id (for diff reports)
 *
 */
function displayUprofilerReport(
    $uprofiler_runs_impl,
    $url_params,
    $source,
    $run,
    $wts,
    $symbol,
    $sort,
    $run1,
    $run2
) {

    if ($run) {                              // specific run to display?

        // run may be a single run or a comma separate list of runs
        // that'll be aggregated. If "wts" (a comma separated list
        // of integral weights is specified), the runs will be
        // aggregated in that ratio.
        //
        $runs_array = explode(",", $run);

        if (count($runs_array) == 1) {
            $uprofiler_data = $uprofiler_runs_impl->get_run($runs_array[0],
                $source,
                $description);
        } else {
            if (! empty( $wts )) {
                $wts_array = explode(",", $wts);
            } else {
                $wts_array = null;
            }
            $data           = uprofiler_aggregate_runs($uprofiler_runs_impl,
                $runs_array, $wts_array, $source, false);
            $uprofiler_data = $data['raw'];
            $description    = $data['description'];
        }


        profiler_single_run_report($url_params,
            $uprofiler_data,
            $description,
            $symbol,
            $sort,
            $run);

    } else if ($run1 && $run2) {                  // diff report for two runs

        $uprofiler_data1 = $uprofiler_runs_impl->get_run($run1, $source, $description1);
        $uprofiler_data2 = $uprofiler_runs_impl->get_run($run2, $source, $description2);

        profiler_diff_report($url_params,
            $uprofiler_data1,
            $description1,
            $uprofiler_data2,
            $description2,
            $symbol,
            $sort,
            $run1,
            $run2);

    } else {
        echo "No uprofiler runs specified in the URL.";
        if (method_exists($uprofiler_runs_impl, 'list_runs')) {
            $uprofiler_runs_impl->list_runs();
        }
    }
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

/**
 * Initialize the metrics we'll display based on the information
 * in the raw data.
 *
 * @author Kannan
 */
function init_metrics($uprofiler_data, $rep_symbol, $sort, $diff_report = false)
{
    global $stats;
    global $pc_stats;
    global $metrics;
    global $diff_mode;
    global $sortable_columns;
    global $sort_col;
    global $display_calls;

    $diff_mode = $diff_report;

    if (! empty( $sort )) {
        if (array_key_exists($sort, $sortable_columns)) {
            $sort_col = $sort;
        } else {
            print( "Invalid Sort Key $sort specified in URL" );
        }
    }

    // For C++ profiler runs, walltime attribute isn't present.
    // In that case, use "samples" as the default sort column.
    if (! isset( $uprofiler_data["main()"]["wt"] )) {

        if ($sort_col == "wt") {
            $sort_col = "samples";
        }

        // C++ profiler data doesn't have call counts.
        // ideally we should check to see if "ct" metric
        // is present for "main()". But currently "ct"
        // metric is artificially set to 1. So, relying
        // on absence of "wt" metric instead.
        $display_calls = false;
    } else {
        $display_calls = true;
    }

    // parent/child report doesn't support exclusive times yet.
    // So, change sort hyperlinks to closest fit.
    if (! empty( $rep_symbol )) {
        $sort_col = str_replace("excl_", "", $sort_col);
    }

    if ($display_calls) {
        $stats = [ "fn", "ct", "Calls%" ];
    } else {
        $stats = [ "fn" ];
    }

    $pc_stats = $stats;

    $possible_metrics = uprofiler_get_possible_metrics($uprofiler_data);
    foreach ($possible_metrics as $metric => $desc) {
        if (isset( $uprofiler_data["main()"][$metric] )) {
            $metrics[] = $metric;
            // flat (top-level reports): we can compute
            // exclusive metrics reports as well.
            $stats[] = $metric;
            $stats[] = "I" . $desc[0] . "%";
            $stats[] = "excl_" . $metric;
            $stats[] = "E" . $desc[0] . "%";

            // parent/child report for a function: we can
            // only breakdown inclusive times correctly.
            $pc_stats[] = $metric;
            $pc_stats[] = "I" . $desc[0] . "%";
        }
    }
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
        uprofiler_error("uprofiler: main() missing in raw data");
        return false;
    }

    // raw data should contain either wall time or samples information...
    if (isset( $main_info["wt"] )) {
        $prune_metric = "wt";
    } else if (isset( $main_info["samples"] )) {
        $prune_metric = "samples";
    } else {
        uprofiler_error("uprofiler: for main() we must have either wt "
                        . "or samples attribute set");
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
        } else if ($parent &&
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