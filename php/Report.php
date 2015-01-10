<?php
namespace Rarst\Sideface;

class Report
{
    protected $body = '';

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

    public function profiler_report(
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
        ob_start();

        global $totals;
        global $totals_1;
        global $totals_2;
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

        $run1_txt              = sprintf('<b>Run #%s:</b> %s', $run1, $run1_desc);
        $base_url_params       = uprofiler_array_unset(uprofiler_array_unset($url_params, 'symbol'), 'all');
        $top_link_query_string = "$base_path/?" . http_build_query($base_url_params);

        if ($diff_mode) {
            $diff_text       = 'Diff';
            $base_url_params = uprofiler_array_unset($base_url_params, 'run1');
            $base_url_params = uprofiler_array_unset($base_url_params, 'run2');
            $run1_link       = uprofiler_render_link(
                'View Run #' . $run1,
                "$base_path/?" . http_build_query(uprofiler_array_set($base_url_params, 'run', $run1))
            );
            $run2_txt        = sprintf('<b>Run #%s:</b> %s', $run2, $run2_desc);
            $run2_link       = uprofiler_render_link(
                'View Run #' . $run2,
                "$base_path/?" . http_build_query(uprofiler_array_set($base_url_params, 'run', $run2))
            );
        } else {
            $diff_text = 'Run';
        }

        // set up the action links for operations that can be done on this report
        $links   = [ ];
        $links[] = uprofiler_render_link("View Top Level $diff_text Report", $top_link_query_string);

        if ($diff_mode) {
            $inverted_params         = $url_params;
            $inverted_params['run1'] = $url_params['run2'];
            $inverted_params['run2'] = $url_params['run1'];

            // view the different runs or invert the current diff
            $links [] = $run1_link;
            $links [] = $run2_link;
            $links [] = uprofiler_render_link(
                'Invert ' . $diff_text . ' Report',
                "$base_path/?" . http_build_query($inverted_params)
            );
        }

        // lookup function typeahead form
        $links [] = '<input class="function_typeahead" type="input" size="40" maxlength="100" />';

        echo uprofiler_render_actions($links);

        echo
            '<dl class=phprof_report_info>' .
            '  <dt>' . $diff_text . ' Report</dt>' .
            '  <dd>' . ( $diff_mode ? $run1_txt . '<br><b>vs.</b><br>' . $run2_txt : $run1_txt ) .
            '  </dd>' .
            '  <dt>Tip</dt>' .
            '  <dd>Click a function name below to drill down.</dd>' .
            '</dl>' .
            '<div style="clear: both; margin: 3em 0;"></div>';

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
                symbol_report(
                    $url_params,
                    $run_delta,
                    $symbol_tab[$rep_symbol],
                    $sort,
                    $rep_symbol,
                    $run1,
                    $info1,
                    $run2,
                    $info2
                );
            } else {
                symbol_report($url_params, $run1_data, $symbol_tab[$rep_symbol], $sort, $rep_symbol, $run1);
            }
        } else {
            /* flat top-level report of all functions */
            full_report($url_params, $symbol_tab, $sort, $run1, $run2);
        }

        $this->body = ob_get_clean();
    }
}
