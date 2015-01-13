<?php
namespace Rarst\Sideface;

use iUprofilerRuns;

class Callgraph
{
    public function render_image(
        iUprofilerRuns $uprofiler_runs_impl,
        $run_id,
        $type,
        $threshold,
        $func,
        $source,
        $critical_path
    ) {

        $content = $this->get_content_by_run(
            $uprofiler_runs_impl,
            $run_id,
            $type,
            $threshold,
            $func,
            $source,
            $critical_path
        );

        if (! $content) {
            print "Error: either we can not find profile data for run_id " . $run_id
                  . " or the threshold " . $threshold . " is too small or you do not"
                  . " have 'dot' image generation utility installed.";
            exit();
        }

        uprofiler_generate_mime_header($type, strlen($content));
        echo $content;
    }

    public function render_diff_image(
        iUprofilerRuns $uprofiler_runs_impl,
        $run1,
        $run2,
        $type,
        $threshold,
        $source
    ) {
        global $total1;
        global $total2;

        $raw_data1 = $uprofiler_runs_impl->get_run($run1, $source, $desc_unused);
        $raw_data2 = $uprofiler_runs_impl->get_run($run2, $source, $desc_unused);
        // init_metrics($raw_data1, null, null);
        //$children_table1 = uprofiler_get_children_table($raw_data1);
        //$children_table2 = uprofiler_get_children_table($raw_data2);
        $symbol_tab1 = uprofiler_compute_flat_info($raw_data1, $total1);
        $symbol_tab2 = uprofiler_compute_flat_info($raw_data2, $total2);
        $run_delta   = uprofiler_compute_diff($raw_data1, $raw_data2);
        $script      = $this->generate_dot_script(
            $run_delta,
            $threshold,
            $source,
            null,
            null,
            true,
            $symbol_tab1,
            $symbol_tab2
        );
        $content     = $this->generate_image_by_dot($script, $type);

        uprofiler_generate_mime_header($type, strlen($content));
        echo $content;
    }

    public function get_content_by_run(
        iUprofilerRuns $uprofiler_runs_impl,
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
            error_log("Raw data is empty");
            return "";
        }

        $script = $this->generate_dot_script(
            $raw_data,
            $threshold,
            $source,
            $description,
            $func,
            $critical_path
        );

        $content = $this->generate_image_by_dot($script, $type);
        return $content;
    }

    public function generate_dot_script(
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
            $node           = 'main()';
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
                            abs($raw_data[uprofiler_build_parent_child_key($node, $child)]['wt']) >
                            abs($raw_data[uprofiler_build_parent_child_key($node, $max_child)]['wt'])
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
        if ($source == 'bm' && array_key_exists('main()', $sym_table)) {
            $total_times  = $sym_table['main()']['ct'];
            $remove_funcs = [
                'main()',
                'hotprofiler_disable',
                'call_user_func_array',
                'uprofiler_disable'
            ];

            foreach ($remove_funcs as $cur_del_func) {
                if (array_key_exists($cur_del_func, $sym_table) &&
                    $sym_table[$cur_del_func]['ct'] == $total_times
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
            if (empty( $func ) && abs($info['wt'] / $totals['wt']) < $threshold) {
                unset( $sym_table[$symbol] );
                continue;
            }
            if ($max_wt == 0 || $max_wt < abs($info['excl_wt'])) {
                $max_wt = abs($info['excl_wt']);
            }
            $sym_table[$symbol]['id'] = $cur_id;
            $cur_id ++;
        }

        // Generate all nodes' information.
        foreach ($sym_table as $symbol => $info) {
            if ($info['excl_wt'] == 0) {
                $sizing_factor = $max_sizing_ratio;
            } else {
                $sizing_factor = $max_wt / abs($info['excl_wt']);
                if ($sizing_factor > $max_sizing_ratio) {
                    $sizing_factor = $max_sizing_ratio;
                }
            }
            $fillcolor = ( ( $sizing_factor < 1.5 ) ? ', style=filled, fillcolor=red' : '' );

            if ($critical_path) {
                // highlight nodes along critical path.
                if (! $fillcolor && array_key_exists($symbol, $path)) {
                    $fillcolor = ', style=filled, fillcolor=yellow';
                }
            }

            $fontsize = ', fontsize=' . (int) ( $max_fontsize / ( ( $sizing_factor - 1 ) / 10 + 1 ) );
            $width    = ', width=' . sprintf("%.1f", $max_width / $sizing_factor);
            $height   = ', height=' . sprintf("%.1f", $max_height / $sizing_factor);

            if ($symbol == 'main()') {
                $shape = 'octagon';
                $name  = 'Total: ' . ( $totals['wt'] / 1000.0 ) . " ms\\n";
                $name .= addslashes(isset( $page ) ? $page : $symbol);
            } else {
                $shape = 'box';
                $name  = addslashes($symbol) . "\\nInc: " . sprintf('%.3f', $info['wt'] / 1000) .
                         ' ms (' . sprintf('%.1f%%', 100 * $info['wt'] / $totals['wt']) . ")";
            }
            if ($left === null) {
                $label = ", label=\"" . $name . "\\nExcl: "
                         . ( sprintf('%.3f', $info['excl_wt'] / 1000.0) ) . ' ms ('
                         . sprintf('%.1f%%', 100 * $info['excl_wt'] / $totals['wt'])
                         . ")\\n" . $info['ct'] . " total calls\"";
            } else {
                if (isset( $left[$symbol] ) && isset( $right[$symbol] )) {
                    $label = ", label=\"" . addslashes($symbol) .
                             "\\nInc: " . ( sprintf('%.3f', $left[$symbol]['wt'] / 1000.0) )
                             . ' ms - '
                             . ( sprintf('%.3f', $right[$symbol]['wt'] / 1000.0) ) . ' ms = '
                             . ( sprintf('%.3f', $info['wt'] / 1000.0) ) . ' ms' .
                             "\\nExcl: "
                             . ( sprintf('%.3f', $left[$symbol]['excl_wt'] / 1000.0) )
                             . ' ms - ' . ( sprintf('%.3f', $right[$symbol]['excl_wt'] / 1000.0) )
                             . ' ms = ' . ( sprintf('%.3f', $info['excl_wt'] / 1000.0) ) . ' ms' .
                             "\\nCalls: " . ( sprintf('%.3f', $left[$symbol]['ct']) ) . ' - '
                             . ( sprintf('%.3f', $right[$symbol]['ct']) ) . ' = '
                             . ( sprintf('%.3f', $info['ct']) ) . "\"";
                } elseif (isset( $left[$symbol] )) {
                    $label = ", label=\"" . addslashes($symbol) .
                             "\\nInc: " . ( sprintf('%.3f', $left[$symbol]['wt'] / 1000.0) )
                             . " ms - 0 ms = " . ( sprintf('%.3f', $info['wt'] / 1000.0) )
                             . ' ms' . "\\nExcl: "
                             . ( sprintf('%.3f', $left[$symbol]['excl_wt'] / 1000.0) )
                             . " ms - 0 ms = "
                             . ( sprintf('%.3f', $info['excl_wt'] / 1000.0) ) . ' ms' .
                             "\\nCalls: " . ( sprintf('%.3f', $left[$symbol]['ct']) ) . ' - 0 = '
                             . ( sprintf('%.3f', $info['ct']) ) . "\"";
                } else {
                    $label = ", label=\"" . addslashes($symbol) .
                             "\\nInc: 0 ms - "
                             . ( sprintf('%.3f', $right[$symbol]['wt'] / 1000.0) )
                             . ' ms = ' . ( sprintf('%.3f', $info['wt'] / 1000.0) ) . ' ms' .
                             "\\nExcl: 0 ms - "
                             . ( sprintf('%.3f', $right[$symbol]['excl_wt'] / 1000.0) )
                             . ' ms = ' . ( sprintf('%.3f', $info['excl_wt'] / 1000.0) ) . ' ms' .
                             "\\nCalls: 0 - " . ( sprintf('%.3f', $right[$symbol]['ct']) )
                             . ' = ' . ( sprintf('%.3f', $info['ct']) ) . "\"";
                }
            }
            $result .= 'N' . $sym_table[$symbol]['id'];
            $result .= "[shape=$shape $label $width $height $fontsize $fillcolor];\n";
        }

        // Generate all the edges' information.
        foreach ($raw_data as $parent_child => $info) {
            list( $parent, $child ) = uprofiler_parse_parent_child($parent_child);

            if (isset( $sym_table[$parent] )
                && isset( $sym_table[$child] )
                && ( empty( $func ) || ( ! empty( $func ) && ( $parent == $func || $child == $func ) ) )
            ) {
                $label = $info['ct'] == 1 ? $info['ct'] . " call" : $info['ct'] . " calls";

                $headlabel = $sym_table[$child]['wt'] > 0
                    ? sprintf('%.1f%%', 100 * $info['wt'] / $sym_table[$child]['wt'])
                    : '0.0%';

                $taillabel = ( $sym_table[$parent]['wt'] > 0 )
                    ? sprintf(
                        '%.1f%%',
                        100 * $info['wt'] / ( $sym_table[$parent]['wt'] - $sym_table[$parent]['excl_wt'] )
                    )
                    : '0.0%';

                $linewidth  = 1;
                $arrow_size = 1;

                if ($critical_path && isset( $path_edges[uprofiler_build_parent_child_key($parent, $child)] )) {
                    $linewidth  = 10;
                    $arrow_size = 2;
                }

                $result .= 'N' . $sym_table[$parent]['id'] . ' -> N' . $sym_table[$child]['id'];
                $result .= "[arrowsize=$arrow_size, color=grey, style=\"setlinewidth($linewidth)\","
                           . " label=\"" . $label
                           . "\", headlabel=\"" . $headlabel
                           . "\", taillabel=\"" . $taillabel
                           . "\" ]";
                $result .= ";\n";
            }
        }
        $result = $result . "\n}";

        return $result;
    }

    public function generate_image_by_dot($dot_script, $type)
    {
        $descriptorspec = [
            0 => [ 'pipe', 'r' ],
            1 => [ 'pipe', 'w' ],
            2 => [ 'pipe', 'w' ]
        ];

        $cmd     = ' dot -T' . $type;
        $process = proc_open($cmd, $descriptorspec, $pipes, sys_get_temp_dir(), [ 'PATH' => getenv('PATH') ]);

        if (is_resource($process)) {
            fwrite($pipes[0], $dot_script);
            fclose($pipes[0]);

            $output = stream_get_contents($pipes[1]);
            $err    = stream_get_contents($pipes[2]);

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
}
