<?php
namespace Rarst\Sideface;

class Report
{
    protected $body = '';
    protected $source = '';
    protected $run = '';

    public function __construct($args)
    {
        $this->source = $args['source'];
        $this->run    = $args['run'];
    }

    public function getBody()
    {
        return $this->body;
    }

    public function init_metrics($uprofiler_data, $rep_symbol, $sort, $diff_report = false)
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
        // In that case, use 'samples' as the default sort column.
        if (! isset( $uprofiler_data['main()']['wt'] )) {
            if ($sort_col == 'wt') {
                $sort_col = 'samples';
            }

            // C++ profiler data doesn't have call counts.
            // ideally we should check to see if 'ct' metric
            // is present for 'main()'. But currently 'ct'
            // metric is artificially set to 1. So, relying
            // on absence of 'wt' metric instead.
            $display_calls = false;
        } else {
            $display_calls = true;
        }

        // parent/child report doesn't support exclusive times yet.
        // So, change sort hyperlinks to closest fit.
        if (! empty( $rep_symbol )) {
            $sort_col = str_replace('excl_', '', $sort_col);
        }

        if ($display_calls) {
            $stats = [ 'fn', 'ct', 'Calls%' ];
        } else {
            $stats = [ 'fn' ];
        }

        $pc_stats = $stats;

        $possible_metrics = uprofiler_get_possible_metrics($uprofiler_data);
        foreach ($possible_metrics as $metric => $desc) {
            if (isset( $uprofiler_data['main()'][$metric] )) {
                $metrics[] = $metric;
                // flat (top-level reports): we can compute
                // exclusive metrics reports as well.
                $stats[] = $metric;
                $stats[] = 'I' . $desc[0] . '%';
                $stats[] = 'excl_' . $metric;
                $stats[] = 'E' . $desc[0] . '%';

                // parent/child report for a function: we can
                // only breakdown inclusive times correctly.
                $pc_stats[] = $metric;
                $pc_stats[] = 'I' . $desc[0] . '%';
            }
        }
    }

    public function profiler_report(
        $url_params,
        $rep_symbol,
        $sort,
        $run1,
        $run1_data,
        $run2 = 0,
        $run2_data = [ ]
    ) {
        ob_start();

        global $totals;
        global $totals_1;
        global $totals_2;
        global $diff_mode;

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

        // data tables
        if (! empty( $rep_symbol )) {
            if (! isset( $symbol_tab[$rep_symbol] )) {
                echo "<hr>Symbol <b>$rep_symbol</b> not found in uprofiler run</b><hr>";
                return;
            }

            /* single function report with parent/child information */
            if ($diff_mode) {
                $info1 = isset( $symbol_tab1[$rep_symbol] ) ? $symbol_tab1[$rep_symbol] : null;
                $info2 = isset( $symbol_tab2[$rep_symbol] ) ? $symbol_tab2[$rep_symbol] : null;
                $this->symbol_report(
                    $url_params,
                    $run_delta,
                    $symbol_tab[$rep_symbol],
                    $rep_symbol,
                    $run1,
                    $info1,
                    $run2,
                    $info2
                );
            } else {
                $this->symbol_report($url_params, $run1_data, $symbol_tab[$rep_symbol], $rep_symbol, $run1);
            }
        } else {
            /* flat top-level report of all functions */
            $this->full_report($url_params, $symbol_tab, $sort, $run1, $run2);
        }

        $this->body = ob_get_clean();
    }

    public function symbol_report(
        $url_params,
        $run_data,
        $symbol_info,
        $rep_symbol,
        $run1,
        $symbol_info1 = null,
        $run2 = 0,
        $symbol_info2 = null
    ) {
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
            $diff_text = '<b>Diff</b>';
            $regr_impr = "<i style='color:red'>Regression</i>/<i style='color:green'>Improvement</i>";
        } else {
            $diff_text = '';
            $regr_impr = '';
        }

        if ($diff_mode) {
            print( "<h3>$regr_impr summary for $rep_symbol</h3>" );
            print( '<table class="table table-condensed">' . "\n" );
            print( '<tr>' );
            print( "<th>$rep_symbol</th>" );
            print( "<th><a href=" . "/{$this->source}/{$run1}" . ">Run #$run1</a></th>" );
            print( "<th><a href=" . "/{$this->source}/{$run2}" . ">Run #$run2</a></th>" );
            print( "<th>Diff</th>" );
            print( "<th>Diff%</th>" );
            print( '</tr>' );
            print( '<tr>' );

            if ($display_calls) {
                print( '<td>Number of Function Calls</td>' );
                print_td_num($symbol_info1['ct'], $format_cbk['ct']);
                print_td_num($symbol_info2['ct'], $format_cbk['ct']);
                print_td_num($symbol_info2['ct'] - $symbol_info1['ct'], $format_cbk['ct'], true);
                print_td_pct($symbol_info2['ct'] - $symbol_info1['ct'], $symbol_info1['ct'], true);
                print( '</tr>' );
            }

            foreach ($metrics as $metric) {
                $m = $metric;

                // Inclusive stat for metric
                print( '<tr>' );
                print( '<td>' . str_replace('<br>', ' ', $descriptions[$m]) . '</td>' );
                print_td_num($symbol_info1[$m], $format_cbk[$m]);
                print_td_num($symbol_info2[$m], $format_cbk[$m]);
                print_td_num($symbol_info2[$m] - $symbol_info1[$m], $format_cbk[$m], true);
                print_td_pct($symbol_info2[$m] - $symbol_info1[$m], $symbol_info1[$m], true);
                print( '</tr>' );

                // AVG (per call) Inclusive stat for metric
                print( '<tr>' );
                print( '<td>' . str_replace('<br>', ' ', $descriptions[$m]) . " per call </td>" );
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
                $m = 'excl_' . $metric;
                print( '<tr>' );
                print( '<td>' . str_replace('<br>', ' ', $descriptions[$m]) . '</td>' );
                print_td_num($symbol_info1[$m], $format_cbk[$m]);
                print_td_num($symbol_info2[$m], $format_cbk[$m]);
                print_td_num($symbol_info2[$m] - $symbol_info1[$m], $format_cbk[$m], true);
                print_td_pct($symbol_info2[$m] - $symbol_info1[$m], $symbol_info1[$m], true);
                print( '</tr>' );
            }

            print( '</table>' );
        }

        print( '<h4>' );
        print( "Parent/Child $regr_impr report for <b>$rep_symbol</b>" );
        print( " <a href=''>[View Callgraph $diff_text]</a>" ); // TODO callgraph link
        print( '</h4>' );

        print( '<table class="table table-condensed">' . "\n" );
        print( '<tr>' );

        foreach ($pc_stats as $stat) {
            $desc = stat_description($stat);
            if (array_key_exists($stat, $sortable_columns)) {
                $href   = "$base_path/?"
                          . http_build_query(uprofiler_array_set($url_params, 'sort', $stat));
                $header = uprofiler_render_link($desc, $href);
            } else {
                $header = $desc;
            }

            if ($stat == 'fn') {
                print( "<th align=left><nobr>$header</th>" );
            } else {
                print( "<th>$header</th>" );
            }
        }
        print( '</tr>' );

        print( '<tr><td>' );
        print( '<b><i>Current Function</i></b>' );
        print( '</td></tr>' );

        print( '<tr>' );
        // make this a self-reference to facilitate copy-pasting snippets to e-mails
        print( "<td><a href=''>$rep_symbol</a>" );
        print( '</td>' );

        if ($display_calls) {
            // Call Count
            print_td_num($symbol_info['ct'], $format_cbk['ct']);
            print_td_pct($symbol_info['ct'], $totals['ct']);
        }

        // Inclusive Metrics for current function
        foreach ($metrics as $metric) {
            print_td_num($symbol_info[$metric], $format_cbk[$metric], ( $sort_col == $metric ));
            print_td_pct($symbol_info[$metric], $totals[$metric], ( $sort_col == $metric ));
        }
        print( '</tr>' );

        print( '<tr>' );
        print( '<td>' . "Exclusive Metrics $diff_text for Current Function</td>" );

        if ($display_calls) {
            // Call Count
            print( '<td></td>' );
            print( '<td></td>' );
        }

        // Exclusive Metrics for current function
        foreach ($metrics as $metric) {
            print_td_num(
                $symbol_info['excl_' . $metric],
                $format_cbk['excl_' . $metric],
                ( $sort_col == $metric ),
                get_tooltip_attributes('Child', $metric)
            );
            print_td_pct(
                $symbol_info['excl_' . $metric],
                $symbol_info[$metric],
                ( $sort_col == $metric ),
                get_tooltip_attributes('Child', $metric)
            );
        }
        print( '</tr>' );

        // list of callers/parent functions
        $results = [ ];
        if ($display_calls) {
            $base_ct = $symbol_info['ct'];
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
                $info_tmp['fn'] = $parent;
                $results[]      = $info_tmp;
            }
        }
        usort($results, 'sort_cbk');

        if (count($results) > 0) {
            $this->print_pc_array($results, $base_ct, $base_info, true);
        }

        // list of callees/child functions
        $results = [ ];
        $base_ct = 0;
        foreach ($run_data as $parent_child => $info) {
            list( $parent, $child ) = uprofiler_parse_parent_child($parent_child);
            if ($parent == $rep_symbol) {
                $info_tmp       = $info;
                $info_tmp['fn'] = $child;
                $results[]      = $info_tmp;
                if ($display_calls) {
                    $base_ct += $info['ct'];
                }
            }
        }
        usort($results, 'sort_cbk');

        if (count($results)) {
            $this->print_pc_array($results, $base_ct, $base_info, false);
        }

        print( '</table>' );

        // These will be used for pop-up tips/help.
        // Related javascript code is in: uprofiler_report.js
        print( "\n" );
        print( '<script language="javascript">' . "\n" );
        print( "var func_name = '\"" . $rep_symbol . "\"';\n" );
        print( "var total_child_ct  = $base_ct;\n" );
        if ($display_calls) {
            print( "var func_ct   = " . $symbol_info['ct'] . ";\n" );
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

    public function full_report($url_params, $symbol_tab, $sort, $run1, $run2)
    {
        global $totals;
        global $totals_1;
        global $totals_2;
        global $metrics;
        global $diff_mode;
        global $descriptions;
        global $sort_col;
        global $format_cbk;
        global $display_calls;

        $possible_metrics = uprofiler_get_possible_metrics();

        if ($diff_mode) {
            print( '<h3>Overall Diff Summary</h3>' );
            print( '<table class="table table-condensed">' . "\n" );
            print( '<tr>' );
            print( '<th></th>' );
            print( '<th>' . uprofiler_render_link("Run #$run1", "/{$this->source}/{$run1}") . '</th>' );
            print( '<th>' . uprofiler_render_link("Run #$run2", "/{$this->source}/{$run2}") . '</th>' );
            print( '<th>Diff</th>' );
            print( '<th>Diff%</th>' );
            print( '</tr>' );

            if ($display_calls) {
                print( '<tr>' );
                print( '<td>Number of Function Calls</td>' );
                print_td_num($totals_1['ct'], $format_cbk['ct']);
                print_td_num($totals_2['ct'], $format_cbk['ct']);
                print_td_num($totals_2['ct'] - $totals_1['ct'], $format_cbk['ct'], true);
                print_td_pct($totals_2['ct'] - $totals_1['ct'], $totals_1['ct'], true);
                print( '</tr>' );
            }

            foreach ($metrics as $metric) {
                $m = $metric;
                print( '<tr>' );
                print( '<td>' . str_replace('<br>', ' ', $descriptions[$m]) . '</td>' );
                print_td_num($totals_1[$m], $format_cbk[$m]);
                print_td_num($totals_2[$m], $format_cbk[$m]);
                print_td_num($totals_2[$m] - $totals_1[$m], $format_cbk[$m], true);
                print_td_pct($totals_2[$m] - $totals_1[$m], $totals_1[$m], true);
                print( '<tr>' );
            }
            print( '</table>' );

            $callgraph_report_title = '[View Regressions/Improvements using Callgraph Diff]';

        } else {
            print( "<p>\n" );

            print( '<table class="table table-condensed">' . "\n" );
            echo '<tr>';
            echo '<th>Overall Summary</th>';
            echo '<th></th>';
            echo '</tr>';

            foreach ($metrics as $metric) {
                echo '<tr>';
                echo '<td>Total ' . str_replace('<br>', ' ', stat_description($metric)) . ':</td>';
                echo '<td>' . number_format($totals[$metric]) . ' '
                     . $possible_metrics[$metric][1] . '</td>';
                echo '</tr>';
            }

            if ($display_calls) {
                echo '<tr>';
                echo '<td>Number of Function Calls:</td>';
                echo '<td>' . number_format($totals['ct']) . '</td>';
                echo '</tr>';
            }

            echo "</table>";
            print( "</p>\n" );

            $callgraph_report_title = '[View Full Callgraph]';
        }

        print(
            '<br><h3>' . uprofiler_render_link($callgraph_report_title, '') . '</h3>' // TODO callgraph link
        );

        $flat_data = [ ];
        foreach ($symbol_tab as $symbol => $info) {
            $tmp         = $info;
            $tmp['fn']   = $symbol;
            $flat_data[] = $tmp;
        }
        usort($flat_data, 'sort_cbk');

        print( '<br>' );

        if (! empty( $url_params['all'] )) {
            $all   = true;
            $limit = 0;    // display all rows
        } else {
            $all   = false;
            $limit = 100;  // display only limited number of rows
        }

        $desc = str_replace('<br>', ' ', $descriptions[$sort_col]);

        if ($diff_mode) {
            if ($all) {
                $title = "Total Diff Report: Sorted by absolute value of regression/improvement in $desc";
            } else {
                $title = "Top 100 <i style='color:red'>Regressions</i>/"
                         . "<i style='color:green'>Improvements</i>: "
                         . "Sorted by $desc Diff";
            }
        } else {
            $title = $all ? "Sorted by $desc" : "Displaying top $limit functions: Sorted by $desc";
        }
        $this->print_flat_data($url_params, $title, $flat_data, $sort, $run1, $run2, $limit);
    }

    public function print_flat_data($url_params, $title, $flat_data, $sort, $run1, $run2, $limit)
    {
        global $stats;
        global $sortable_columns;
        global $base_path;

        $size = count($flat_data);
        if (! $limit) {
            $limit        = $size;
            $display_link = '';
        } else {
            $display_link = uprofiler_render_link(
                ' [ <b>display all</b>]',
                "$base_path/?" .
                http_build_query(uprofiler_array_set($url_params, 'all', 1))
            );
        }

        print( "<h3 align=center>$title $display_link</h3><br>" );

        print( '<table class="table table-condensed">' );
        print( '<tr>' );

        foreach ($stats as $stat) {
            $desc = stat_description($stat);
            if (array_key_exists($stat, $sortable_columns)) {
                $href   = "$base_path/?" . http_build_query(uprofiler_array_set($url_params, 'sort', $stat));
                $header = uprofiler_render_link($desc, $href);
            } else {
                $header = $desc;
            }

            print( "<th>$header</th>" );
        }
        print( "</tr>\n" );

        if ($limit >= 0) {
            $limit = min($size, $limit);
            for ($i = 0; $i < $limit; $i ++) {
                $this->print_function_info($flat_data[$i]);
            }
        } else {
            // if $limit is negative, print abs($limit) items starting from the end
            $limit = min($size, abs($limit));
            for ($i = 0; $i < $limit; $i ++) {
                $this->print_function_info($flat_data[$size - $i - 1]);
            }
        }
        print( '</table>' );

        // let's print the display all link at the bottom as well...
        if ($display_link) {
            echo $display_link;
        }
    }

    public function print_function_info($info)
    {
        global $totals;
        global $sort_col;
        global $metrics;
        global $format_cbk;
        global $display_calls;

        print( '<tr>' );

        $href = "/{$this->source}/{$this->run}/{$info['fn']}";

        print( '<td>' );
        print( uprofiler_render_link($info['fn'], $href) );
        print( "</td>\n" );

        if ($display_calls) {
            // Call Count..
            print_td_num($info['ct'], $format_cbk['ct'], ( $sort_col == 'ct' ));
            print_td_pct($info['ct'], $totals['ct'], ( $sort_col == 'ct' ));
        }

        // Other metrics..
        foreach ($metrics as $metric) {
            // Inclusive metric
            print_td_num($info[$metric], $format_cbk[$metric], ( $sort_col == $metric ));
            print_td_pct($info[$metric], $totals[$metric], ( $sort_col == $metric ));

            // Exclusive Metric
            print_td_num($info['excl_' . $metric], $format_cbk['excl_' . $metric], ( $sort_col == 'excl_' . $metric ));
            print_td_pct($info['excl_' . $metric], $totals[$metric], ( $sort_col == 'excl_' . $metric ));
        }

        print( "</tr>\n" );
    }

    public function print_pc_array($results, $base_ct, $base_info, $parent)
    {
        $title = $parent ? 'Parent function' : 'Child function';
        if (count($results) > 1) {
            $title .= 's';
        }
        print( '<tr><td>' );
        print( '<b><i>' . $title . '</i></b>' );
        print( '</td></tr>' );

        foreach ($results as $info) {
            print( '<tr>' );
            print( '<td>' . uprofiler_render_link($info['fn'], "/{$this->source}/{$this->run}/{$info['fn']}") );
            print( '</td>' );
            $this->pc_info($info, $base_ct, $base_info, $parent);
            print( '</tr>' );
        }
    }

    public function pc_info($info, $base_ct, $base_info, $parent)
    {
        global $sort_col;
        global $metrics;
        global $format_cbk;
        global $display_calls;

        $type = $parent ? 'Parent' : 'Child';

        if ($display_calls) {
            $mouseoverct = get_tooltip_attributes($type, 'ct');
            /* call count */
            print_td_num($info['ct'], $format_cbk['ct'], ( $sort_col == 'ct' ), $mouseoverct);
            print_td_pct($info['ct'], $base_ct, ( $sort_col == 'ct' ), $mouseoverct);
        }

        /* Inclusive metric values  */
        foreach ($metrics as $metric) {
            print_td_num(
                $info[$metric],
                $format_cbk[$metric],
                ( $sort_col == $metric ),
                get_tooltip_attributes($type, $metric)
            );
            print_td_pct(
                $info[$metric],
                $base_info[$metric],
                ( $sort_col == $metric ),
                get_tooltip_attributes($type, $metric)
            );
        }
    }
}
