<?php
namespace Rarst\Sideface;

use iUprofilerRuns;

class Report
{
    protected $body = '';
    protected $source = '';
    protected $run = '';

    protected $sort_col = 'wt';
    protected $diff_mode = false;
    protected $stats = [ ];
    protected $pc_stats = [ ];
    protected $totals = [ ];
    protected $totals_1 = [ ];
    protected $totals_2 = [ ];
    protected $metrics = [ ];

    protected $descriptions = [
        'fn'           => 'Function Name',
        'ct'           => 'Calls',
        'Calls%'       => 'Calls, %',
        'wt'           => '● Wall Time, μs',
        'IWall%'       => '● Wall, %',
        'excl_wt'      => '○ Wall Time, μs',
        'EWall%'       => '○ Wall, %',
        'ut'           => '● User, μs',
        'IUser%'       => '● User, %',
        'excl_ut'      => '○ User, μs',
        'EUser%'       => '○ User, %',
        'st'           => '● Sys, μs',
        'ISys%'        => '● Sys, %',
        'excl_st'      => '○ Sys, μs',
        'ESys%'        => '○ Sys, %',
        'cpu'          => '● CPU, μs',
        'ICpu%'        => '● CPU, %',
        'excl_cpu'     => '○ CPU, μs',
        'ECpu%'        => '○ CPU, %',
        'mu'           => '● Memory, B',
        'IMUse%'       => '● Memory, %',
        'excl_mu'      => '○ Memory, B',
        'EMUse%'       => '○ Memory, %',
        'pmu'          => '● Peak Memory, B',
        'IPMUse%'      => '● Peak Memory, %',
        'excl_pmu'     => '○ Peak Memory, B',
        'EPMUse%'      => '○ Peak Memory, %',
        'samples'      => '● Samples',
        'ISamples%'    => '● Samples, %',
        'excl_samples' => '○ Samples',
        'ESamples%'    => '○ Samples, %',
    ];

    protected $sortable_columns = [
        'fn',
        'ct',
        'wt',
        'excl_wt',
        'ut',
        'excl_ut',
        'st',
        'excl_st',
        'mu',
        'excl_mu',
        'pmu',
        'excl_pmu',
        'cpu',
        'excl_cpu',
        'samples',
        'excl_samples',
    ];

    protected $format_cbk = [
        'fn'           => '',
        'ct'           => 'uprofiler_count_format',
        'Calls%'       => 'uprofiler_percent_format',
        'wt'           => 'number_format',
        'IWall%'       => 'uprofiler_percent_format',
        'excl_wt'      => 'number_format',
        'EWall%'       => 'uprofiler_percent_format',
        'ut'           => 'number_format',
        'IUser%'       => 'uprofiler_percent_format',
        'excl_ut'      => 'number_format',
        'EUser%'       => 'uprofiler_percent_format',
        'st'           => 'number_format',
        'ISys%'        => 'uprofiler_percent_format',
        'excl_st'      => 'number_format',
        'ESys%'        => 'uprofiler_percent_format',
        'cpu'          => 'number_format',
        'ICpu%'        => 'uprofiler_percent_format',
        'excl_cpu'     => 'number_format',
        'ECpu%'        => 'uprofiler_percent_format',
        'mu'           => 'number_format',
        'IMUse%'       => 'uprofiler_percent_format',
        'excl_mu'      => 'number_format',
        'EMUse%'       => 'uprofiler_percent_format',
        'pmu'          => 'number_format',
        'IPMUse%'      => 'uprofiler_percent_format',
        'excl_pmu'     => 'number_format',
        'EPMUse%'      => 'uprofiler_percent_format',
        'samples'      => 'number_format',
        'ISamples%'    => 'uprofiler_percent_format',
        'excl_samples' => 'number_format',
        'ESamples%'    => 'uprofiler_percent_format',
    ];

    public function __construct($args)
    {
        $this->source = $args['source'];
        $this->run    = $args['run'];
    }

    public function getBody()
    {
        return $this->body;
    }

    public function initMetrics($data, $symbol, $sort, $diff_report = false)
    {
        $this->diff_mode = $diff_report;

        if (! empty( $sort )) {
            if (in_array($sort, $this->sortable_columns)) {
                $this->sort_col = $sort;
            } else {
                print( "Invalid Sort Key $sort specified in URL" );
            }
        }

        // parent/child report doesn't support exclusive times yet.
        // So, change sort hyperlinks to closest fit.
        if (! empty( $symbol )) {
            $this->sort_col = str_replace('excl_', '', $this->sort_col);
        }

        $this->stats      = [ 'fn', 'ct', 'Calls%' ];
        $this->pc_stats   = $this->stats;
        $possible_metrics = uprofiler_get_possible_metrics($data);

        foreach ($possible_metrics as $metric => $desc) {
            if (isset( $data['main()'][$metric] )) {
                $this->metrics[] = $metric;
                // flat (top-level reports): we can compute
                // exclusive metrics reports as well.
                $this->stats[] = $metric;
                $this->stats[] = 'I' . $desc[0] . '%';
                $this->stats[] = 'excl_' . $metric;
                $this->stats[] = 'E' . $desc[0] . '%';

                // parent/child report for a function: we can
                // only breakdown inclusive times correctly.
                $this->pc_stats[] = $metric;
                $this->pc_stats[] = 'I' . $desc[0] . '%';
            }
        }
    }

    /**
     * @param iUprofilerRuns $uprofiler_runs_impl An object that implements the iUprofilerRuns interface
     * @param  array         $runs                run ids of the uprofiler runs..
     * @param  array         $wts                 integral (ideally) weights for $runs
     * @param  string        $source              source to fetch raw data for run from
     * @param  bool          $use_script_name     If true, a fake edge from main() to
     *                                            to __script::<scriptname> is introduced
     *                                            in the raw data so that after aggregations
     *                                            the script name is still preserved.
     *
     * @return array Return aggregated raw data
     */
    public function aggregate_runs(
        iUprofilerRuns $uprofiler_runs_impl,
        $runs,
        $wts,
        $source = 'phprof',
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
                foreach ($raw_data['main()'] as $metric => $val) {
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

            if (! $this->is_valid_run($run_id, $raw_data)) {
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
                    $raw_data["main()"]                                                      = $new_main;
                    $raw_data[uprofiler_build_parent_child_key('main()', "__script::$page")] = $fake_edge;
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
                    if (substr($parent_child, 0, 9) == 'main()==>') {
                        $child = substr($parent_child, 9);
                        // ignore the newly added edge from main()
                        if (substr($child, 0, 10) != '__script::') {
                            $parent_child = uprofiler_build_parent_child_key("__script::$page", $child);
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

        $run_count           = $run_count - count($bad_runs);
        $data['description'] = "Aggregated Report for $run_count runs:  {$runs_string} {$wts_string}\n";
        $data['raw']         = $this->normalizeMetrics($raw_data_total, $normalization_count);
        $data['bad_runs']    = $bad_runs;

        return $data;
    }

    /**
     * @param array   $data
     * @param integer $runs
     *
     * @return array
     */
    public function normalizeMetrics($data, $runs)
    {
        if (empty( $data ) || ( $runs == 0 )) {
            return $data;
        }

        $normalized = [ ];

        if (isset( $data['==>main()'] ) && isset( $data['main()'] )) {
            error_log('Error: both ==>main() and main() set in raw data.');
        }

        foreach ($data as $parent_child => $info) {
            foreach ($info as $metric => $value) {
                $normalized[$parent_child][$metric] = ( $value / $runs );
            }
        }

        return $normalized;
    }

    /**
     * @param   int   $run_id   Run id of run to be pruned.[Used only for reporting errors.]
     * @param   array $raw_data uprofiler raw data to be pruned & validated.
     *
     * @return bool
     */
    public function is_valid_run($run_id, $raw_data)
    {
        $main_info = $raw_data["main()"];
        if (empty( $main_info )) {
            error_log("uprofiler: main() missing in raw data for Run ID: $run_id");
            return false;
        }

        // raw data should contain either wall time or samples information...
        if (isset( $main_info['wt'] )) {
            $metric = 'wt';
        } elseif (isset( $main_info['samples'] )) {
            $metric = 'samples';
        } else {
            error_log("uprofiler: Wall Time information missing from Run ID: $run_id");
            return false;
        }

        foreach ($raw_data as $info) {
            $val = $info[$metric];

            // basic sanity checks...
            if ($val < 0) {
                error_log("uprofiler: $metric should not be negative: Run ID $run_id" . serialize($info));
                return false;
            }
            if ($val > ( 86400000000 )) {
                error_log("uprofiler: $metric > 1 day found in Run ID: $run_id " . serialize($info));
                return false;
            }
        }
        return true;
    }


    /**
     * @param RunInterface $run
     * @param string       $symbol
     */
    public function profilerReport(RunInterface $run, $symbol = '')
    {
        ob_start();
        $runData = $run->getData();
        $this->initMetrics($runData, $symbol, 'wt', false);

        if (! empty( $symbol )) {
            $runData = $this->trimRun($runData, [ $symbol ]);
        }

        $runDataObject = new RunData($runData);
        $symbol_tab    = $runDataObject->getFlat();
        $this->totals  = $runDataObject->getTotals();

        if (! empty( $symbol ) && ! isset( $symbol_tab[$symbol] )) {
            echo "Symbol {$symbol} not found in uprofiler run";
            return;
        }

        if (empty( $symbol )) {
            $this->full_report($symbol_tab, $run->getId(), null);
        } else {
            $this->symbol_report($runData, $symbol_tab[$symbol], $symbol, $run->getId());
        }

        $this->body = ob_get_clean();
    }

    /**
     * @param RunInterface $run1
     * @param RunInterface $run2
     * @param string       $symbol
     */
    public function profilerDiffReport(RunInterface $run1, RunInterface $run2, $symbol = '')
    {
        ob_start();

        $run1_data = $run1->getData();
        $run2_data = $run2->getData();
        $this->initMetrics($run2_data, $symbol, 'wt', true);

        if (! empty( $symbol )) {
            $run1_data = $this->trimRun($run1_data, [ $symbol ]);
            $run2_data = $this->trimRun($run2_data, [ $symbol ]);
        }

        $runDataObject = new RunData($run1_data);
        $run_delta     = $runDataObject->diffTo($run2_data);
        $runDataObject = new RunData($run_delta);
        $symbol_tab    = $runDataObject->getFlat();
        $this->totals  = $runDataObject->getTotals();

        if (! empty( $symbol ) && ! isset( $symbol_tab[$symbol] )) {
            echo "Symbol {$symbol} not found in uprofiler run";
            return;
        }

        $runDataObject  = new RunData($run1_data);
        $symbol_tab1    = $runDataObject->getFlat();
        $this->totals_1 = $runDataObject->getTotals();
        $runDataObject  = new RunData($run2_data);
        $symbol_tab2    = $runDataObject->getFlat();
        $this->totals_2 = $runDataObject->getTotals();

        if (empty( $symbol )) {
            $this->full_report($symbol_tab, $run1->getId(), $run2->getId());
        } else {
            $info1 = isset( $symbol_tab1[$symbol] ) ? $symbol_tab1[$symbol] : null;
            $info2 = isset( $symbol_tab2[$symbol] ) ? $symbol_tab2[$symbol] : null;
            $this->symbol_report(
                $run_delta,
                $symbol_tab[$symbol],
                $symbol,
                $run1->getId(),
                $info1,
                $run2->getId(),
                $info2
            );
        }

        $this->body = ob_get_clean();
    }

    /**
     * @param array $data
     * @param array $keep
     *
     * @return array
     */
    public function trimRun($data, $keep)
    {
        $keep[]  = 'main()';
        $trimmed = [ ];

        foreach ($data as $parent_child => $info) {
            list( $parent, $child ) = uprofiler_parse_parent_child($parent_child);

            if (in_array($parent, $keep) || in_array($child, $keep)) {
                $trimmed[$parent_child] = $info;
            }
        }

        return $trimmed;
    }

    public function symbol_report(
        $run_data,
        $symbol_info,
        $rep_symbol,
        $run1,
        $symbol_info1 = null,
        $run2 = 0,
        $symbol_info2 = null
    ) {
        $possible_metrics = uprofiler_get_possible_metrics();

        if ($this->diff_mode) {
            $diff_text = '<b>Diff</b>';
            $regr_impr = "<i style='color:red'>Regression</i>/<i style='color:green'>Improvement</i>";
        } else {
            $diff_text = '';
            $regr_impr = '';
        }

        if ($this->diff_mode) {
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
            print( '<td>Number of Function Calls</td>' );
            $this->print_td_num($symbol_info1['ct'], $this->format_cbk['ct']);
            $this->print_td_num($symbol_info2['ct'], $this->format_cbk['ct']);
            $this->print_td_num($symbol_info2['ct'] - $symbol_info1['ct'], $this->format_cbk['ct']);
            $this->print_td_pct($symbol_info2['ct'] - $symbol_info1['ct'], $symbol_info1['ct']);
            print( '</tr>' );

            foreach ($this->metrics as $metric) {
                $m = $metric;

                // Inclusive stat for metric
                print( '<tr>' );
                print( '<td>' . str_replace('<br>', ' ', $this->descriptions[$m]) . '</td>' );
                $this->print_td_num($symbol_info1[$m], $this->format_cbk[$m]);
                $this->print_td_num($symbol_info2[$m], $this->format_cbk[$m]);
                $this->print_td_num($symbol_info2[$m] - $symbol_info1[$m], $this->format_cbk[$m]);
                $this->print_td_pct($symbol_info2[$m] - $symbol_info1[$m], $symbol_info1[$m]);
                print( '</tr>' );

                // AVG (per call) Inclusive stat for metric
                print( '<tr>' );
                print( '<td>' . str_replace('<br>', ' ', $this->descriptions[$m]) . " per call </td>" );
                $avg_info1 = 'N/A';
                $avg_info2 = 'N/A';
                if ($symbol_info1['ct'] > 0) {
                    $avg_info1 = ( $symbol_info1[$m] / $symbol_info1['ct'] );
                }
                if ($symbol_info2['ct'] > 0) {
                    $avg_info2 = ( $symbol_info2[$m] / $symbol_info2['ct'] );
                }
                $this->print_td_num($avg_info1, $this->format_cbk[$m]);
                $this->print_td_num($avg_info2, $this->format_cbk[$m]);
                $this->print_td_num($avg_info2 - $avg_info1, $this->format_cbk[$m]);
                $this->print_td_pct($avg_info2 - $avg_info1, $avg_info1);
                print( '</tr>' );

                // Exclusive stat for metric
                $m = 'excl_' . $metric;
                print( '<tr>' );
                print( '<td>' . str_replace('<br>', ' ', $this->descriptions[$m]) . '</td>' );
                $this->print_td_num($symbol_info1[$m], $this->format_cbk[$m]);
                $this->print_td_num($symbol_info2[$m], $this->format_cbk[$m]);
                $this->print_td_num($symbol_info2[$m] - $symbol_info1[$m], $this->format_cbk[$m]);
                $this->print_td_pct($symbol_info2[$m] - $symbol_info1[$m], $symbol_info1[$m]);
                print( '</tr>' );
            }

            print( '</table>' );
        }

        print( '<h4>' );
        print( "Parent/Child $regr_impr report for <b>$rep_symbol</b>" );
        print( " <a href='/{$this->source}/{$run1}-{$run2}/{$rep_symbol}/callgraph'>[View Callgraph $diff_text]</a>" );
        print( '</h4>' );

        print( '<table class="table table-condensed">' . "\n" );
        print( '<tr>' );

        foreach ($this->pc_stats as $stat) {
            $desc = $this->stat_description($stat);
            if (in_array($stat, $this->sortable_columns)) {
                $header = "<a href=''>{$desc}</a>"; // TODO sort link
            } else {
                $header = $desc;
            }

            print( "<th>$header</th>" );
        }
        print( '</tr>' );

        print( '<tr><td>' );
        print( '<b><i>Current Function</i></b>' );
        print( '</td></tr>' );

        print( '<tr>' );
        // make this a self-reference to facilitate copy-pasting snippets to e-mails
        print( "<td><a href=''>$rep_symbol</a>" );
        print( '</td>' );

        $this->print_td_num($symbol_info['ct'], $this->format_cbk['ct']);
        $this->print_td_pct($symbol_info['ct'], $this->totals['ct']);

        // Inclusive Metrics for current function
        foreach ($this->metrics as $metric) {
            $this->print_td_num($symbol_info[$metric], $this->format_cbk[$metric]);
            $this->print_td_pct($symbol_info[$metric], $this->totals[$metric]);
        }
        print( '</tr>' );

        print( '<tr>' );
        print( '<td>' . "Exclusive Metrics $diff_text for Current Function</td>" );
        print( '<td></td>' );
        print( '<td></td>' );

        // Exclusive Metrics for current function
        foreach ($this->metrics as $metric) {
            $this->print_td_num(
                $symbol_info['excl_' . $metric],
                $this->format_cbk['excl_' . $metric],
                "type='Child' metric='{$metric}'"
            );
            $this->print_td_pct(
                $symbol_info['excl_' . $metric],
                $symbol_info[$metric],
                "type='Child' metric='{$metric}'"
            );
        }
        print( '</tr>' );

        // list of callers/parent functions
        $results = [ ];
        $base_ct = $symbol_info['ct'];
        foreach ($this->metrics as $metric) {
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
        usort($results, [ $this, 'sortCallback' ]);

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
                $base_ct += $info['ct'];
            }
        }
        usort($results, [ $this, 'sortCallback' ]);

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
        print( "var func_ct   = " . $symbol_info['ct'] . ";\n" );
        print( "var func_metrics = new Array();\n" );
        print( "var metrics_col  = new Array();\n" );
        print( "var metrics_desc  = new Array();\n" );
        if ($this->diff_mode) {
            print( "var diff_mode = true;\n" );
        } else {
            print( "var diff_mode = false;\n" );
        }
        $column_index = 3; // First three columns are Func Name, Calls, Calls%
        foreach ($this->metrics as $metric) {
            print( "func_metrics[\"" . $metric . "\"] = " . round($symbol_info[$metric]) . ";\n" );
            print( "metrics_col[\"" . $metric . "\"] = " . $column_index . ";\n" );
            print( "metrics_desc[\"" . $metric . "\"] = \"" . $possible_metrics[$metric][2] . "\";\n" );

            // each metric has two columns..
            $column_index += 2;
        }
        print( '</script>' );
        print( "\n" );

    }

    public function full_report($symbol_tab, $run1, $run2)
    {
        $possible_metrics = uprofiler_get_possible_metrics();

        if ($this->diff_mode) {
            print( '<h3>Overall Diff Summary</h3>' );
            print( '<table class="table table-condensed">' . "\n" );
            print( '<tr>' );
            print( '<th></th>' );
            print( '<th>' . "<a href='/{$this->source}/{$run1}'>Run #{$run1}</a>" . '</th>' );
            print( '<th>' . "<a href='/{$this->source}/{$run2}'>Run #{$run2}</a>" . '</th>' );
            print( '<th>Diff</th>' );
            print( '<th>Diff%</th>' );
            print( '</tr>' );
            print( '<tr>' );
            print( '<td>Number of Function Calls</td>' );
            $this->print_td_num($this->totals_1['ct'], $this->format_cbk['ct']);
            $this->print_td_num($this->totals_2['ct'], $this->format_cbk['ct']);
            $this->print_td_num($this->totals_2['ct'] - $this->totals_1['ct'], $this->format_cbk['ct']);
            $this->print_td_pct($this->totals_2['ct'] - $this->totals_1['ct'], $this->totals_1['ct']);
            print( '</tr>' );

            foreach ($this->metrics as $metric) {
                $m = $metric;
                print( '<tr>' );
                print( '<td>' . str_replace('<br>', ' ', $this->descriptions[$m]) . '</td>' );
                $this->print_td_num($this->totals_1[$m], $this->format_cbk[$m]);
                $this->print_td_num($this->totals_2[$m], $this->format_cbk[$m]);
                $this->print_td_num($this->totals_2[$m] - $this->totals_1[$m], $this->format_cbk[$m]);
                $this->print_td_pct($this->totals_2[$m] - $this->totals_1[$m], $this->totals_1[$m]);
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

            foreach ($this->metrics as $metric) {
                echo '<tr>';
                echo '<td>Total ' . str_replace('<br>', ' ', $this->stat_description($metric)) . ':</td>';
                echo '<td>' . number_format($this->totals[$metric]) . ' '
                     . $possible_metrics[$metric][1] . '</td>';
                echo '</tr>';
            }

            echo '<tr>';
            echo '<td>Number of Function Calls:</td>';
            echo '<td>' . number_format($this->totals['ct']) . '</td>';
            echo '</tr>';
            echo "</table>";
            print( "</p>\n" );

            $callgraph_report_title = '[View Full Callgraph]';
        }

        $callgraphLink = $run2 ? "/{$this->source}/{$run1}-{$run2}/callgraph" : "/{$this->source}/{$run1}/callgraph";
        print(
            '<br><h3>' . "<a href='{$callgraphLink}'>{$callgraph_report_title}</a>" . '</h3>'
        );

        $flat_data = [ ];
        foreach ($symbol_tab as $symbol => $info) {
            $tmp         = $info;
            $tmp['fn']   = $symbol;
            $flat_data[] = $tmp;
        }
        usort($flat_data, [ $this, 'sortCallback' ]);

        print( '<br>' );

        if (! empty( $_GET['all'] )) {
            $all   = true;
            $limit = 0;    // display all rows
        } else {
            $all   = false;
            $limit = 100;  // display only limited number of rows
        }

        $desc = str_replace('<br>', ' ', $this->descriptions[$this->sort_col]);

        if ($this->diff_mode) {
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
        $this->print_flat_data($title, $flat_data, $limit);
    }

    public function print_flat_data($title, $flat_data, $limit)
    {
        $size = count($flat_data);
        if (! $limit) {
            $limit        = $size;
            $display_link = '';
        } else {
            $display_link = "<a href=''>[<b>display all</b>]</a>"; // TODO display all link
        }

        print( "<h3 align=center>$title $display_link</h3><br>" );

        print( '<table class="table table-condensed">' );
        print( '<tr>' );

        foreach ($this->stats as $stat) {
            $desc = $this->stat_description($stat);
            if (in_array($stat, $this->sortable_columns)) {
                $header = "<a href=''>$desc</a>"; // TODO sort link
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
        print( '<tr>' );

        $href = "/{$this->source}/{$this->run}/{$info['fn']}";

        print( '<td>' );
        print( "<a href='$href'>{$info['fn']}</a>" );
        print( "</td>\n" );

        $this->print_td_num($info['ct'], $this->format_cbk['ct']);
        $this->print_td_pct($info['ct'], $this->totals['ct']);

        // Other metrics..
        foreach ($this->metrics as $metric) {
            // Inclusive metric
            $this->print_td_num($info[$metric], $this->format_cbk[$metric]);
            $this->print_td_pct($info[$metric], $this->totals[$metric]);

            // Exclusive Metric
            $this->print_td_num($info['excl_' . $metric], $this->format_cbk['excl_' . $metric]);
            $this->print_td_pct($info['excl_' . $metric], $this->totals[$metric]);
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
            print( '<td>' . "<a href='/{$this->source}/{$this->run}/{$info['fn']}'>{$info['fn']}</a>" );
            print( '</td>' );
            $this->pc_info($info, $base_ct, $base_info, $parent);
            print( '</tr>' );
        }
    }

    public function pc_info($info, $base_ct, $base_info, $parent)
    {
        $type        = $parent ? 'Parent' : 'Child';
        $mouseoverct = "type='{$type}' metric='ct'";
        $this->print_td_num($info['ct'], $this->format_cbk['ct'], $mouseoverct);
        $this->print_td_pct($info['ct'], $base_ct, $mouseoverct);

        /* Inclusive metric values  */
        foreach ($this->metrics as $metric) {
            $this->print_td_num($info[$metric], $this->format_cbk[$metric], "type='{$type}' metric='{$metric}'");
            $this->print_td_pct($info[$metric], $base_info[$metric], "type='{$type}' metric='{$metric}'");
        }
    }

    public function print_td_num($num, $fmt_func, $attributes = null)
    {
        if (! empty( $fmt_func ) && is_numeric($num)) {
            $num = call_user_func($fmt_func, $num);
        }

        print( "<td $attributes>$num</td>\n" );
    }

    public function print_td_pct($number, $denom, $attributes = null)
    {
        $pct = ( 0 == $denom ) ? 'N/A%' : uprofiler_percent_format($number / abs($denom));

        print( "<td $attributes>$pct</td>\n" );
    }

    public function stat_description($stat)
    {
        return $this->descriptions[$stat];
    }

    public function sortCallback($a, $b)
    {
        if ($this->sort_col == 'fn') {
            $left  = strtoupper($a['fn']);
            $right = strtoupper($b['fn']);

            if ($left == $right) {
                return 0;
            }
            return ( $left < $right ) ? - 1 : 1;
        } else {
            $left  = $a[$this->sort_col];
            $right = $b[$this->sort_col];

            if ($this->diff_mode) {
                $left  = abs($left);
                $right = abs($right);
            }

            if ($left == $right) {
                return 0;
            }
            return ( $left > $right ) ? - 1 : 1;
        }
    }
}
