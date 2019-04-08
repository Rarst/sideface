<?php
namespace Rarst\Sideface;

class Callgraph
{
    protected $legal_image_types = [ 'jpg', 'gif', 'png', 'svg', 'ps' ];

    /** @var float */
    protected $threshold;

    /** @var string */
    protected $type;

    /** @var bool */
    protected $critical;

    /** @var string */
    protected $func;

    public function __construct(array $args = [ ])
    {
        if (empty($args['threshold']) || $args['threshold'] < 0 || $args['threshold'] > 1) {
            $this->threshold = '0.01';
        } else {
            $this->threshold = $args['threshold'];
        }

        if (empty($args['type']) || ! in_array($args['type'], $this->legal_image_types)) {
            $this->type = 'svg';
        } else {
            $this->type = $args['type'];
        }

        $this->critical = isset($args['critical']) ? (bool) $args['critical'] : true;
        $this->func     = empty($args['func']) ? '' : $args['func'];
    }

    public function render_image(RunInterface $run)
    {
        $content = $this->get_content_by_run($run);

        if (! $content) {
            print 'Error: either we can not find profile data for run_id ' . $run->getId()
                  . ' or the threshold ' . $this->threshold . ' is too small or you do not'
                  . " have 'dot' image generation utility installed.";
            exit();
        }

        $this->generate_mime_header(strlen($content));
        echo $content;
    }

    public function render_diff_image(RunInterface $run1, RunInterface $run2)
    {
        $raw_data1      = $run1->getData();
        $raw_data2      = $run2->getData();
        $runDataObject1 = new RunData($raw_data1);
        $symbol_tab1    = $runDataObject1->getFlat();
        $runDataObject2 = new RunData($raw_data2);
        $symbol_tab2    = $runDataObject2->getFlat();
        $run_delta      = $runDataObject1->diffTo($raw_data2);
        $script         = $this->generate_dot_script($run_delta, $run1->getSource(), null, $symbol_tab1, $symbol_tab2);
        $content        = $this->generate_image_by_dot($script);

        $this->generate_mime_header(strlen($content));
        echo $content;
    }

    public function get_content_by_run(RunInterface $run)
    {
        $raw_data = $run->getData();
        $script   = $this->generate_dot_script($raw_data, $run->getSource(), '');
        $content  = $this->generate_image_by_dot($script);

        return $content;
    }

    public function generate_dot_script(
        $raw_data,
        $source,
        $page,
        $right = null,
        $left = null
    ) {
        $max_width        = 5;
        $max_height       = 3.5;
        $max_fontsize     = 35;
        $max_sizing_ratio = 20;

//        if ($left === null) {
            // init_metrics($raw_data, null, null);
//        }

        $runDataObject = new RunData($raw_data);
        $sym_table     = $runDataObject->getFlat();
        $totals        = $runDataObject->getTotals();

        if ($this->critical) {
            $children_table = $this->get_children_table($raw_data);
            $node           = 'main()';
            $path           = [ ];
            $path_edges     = [ ];
            $visited        = [ ];
            while ($node) {
                $visited[$node] = true;
                if (isset($children_table[$node])) {
                    $max_child = null;
                    foreach ($children_table[$node] as $child) {
                        if (isset($visited[$child])) {
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
                    unset($sym_table[$cur_del_func]);
                }
            }
        }

        // use the function to filter out irrelevant functions.
        if (! empty($this->func)) {
            $interested_funcs = [ ];
            foreach ($raw_data as $parent_child => $info) {
                list( $parent, $child ) = uprofiler_parse_parent_child($parent_child);
                if ($parent == $this->func || $child == $this->func) {
                    $interested_funcs[$parent] = 1;
                    $interested_funcs[$child]  = 1;
                }
            }
            foreach ($sym_table as $symbol => $info) {
                if (! array_key_exists($symbol, $interested_funcs)) {
                    unset($sym_table[$symbol]);
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
            if (empty($this->func) && abs($info['wt'] / $totals['wt']) < $this->threshold) {
                unset($sym_table[$symbol]);
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

            // highlight nodes along critical path.
            if ($this->critical && ! $fillcolor && array_key_exists($symbol, $path)) {
                $fillcolor = ', style=filled, fillcolor=yellow';
            }

            $fontsize = ', fontsize=' . (int) ( $max_fontsize / ( ( $sizing_factor - 1 ) / 10 + 1 ) );
            $width    = ', width=' . sprintf('%.1f', $max_width / $sizing_factor);
            $height   = ', height=' . sprintf('%.1f', $max_height / $sizing_factor);

            if ($symbol == 'main()') {
                $shape = 'octagon';
                $name  = 'Total: ' . ( $totals['wt'] / 1000.0 ) . " ms\\n";
                $name .= addslashes(isset($page) ? $page : $symbol);
            } else {
                $shape = 'box';
                $name  = addslashes($symbol) . "\\nInc: " . sprintf('%.3f', $info['wt'] / 1000) .
                         ' ms (' . sprintf('%.1f%%', 100 * $info['wt'] / $totals['wt']) . ')';
            }

            if ($left === null) {
                $label = ', label="' . $name . "\\nExcl: "
                         . sprintf('%.3f', $info['excl_wt'] / 1000.0) . ' ms ('
                         . sprintf('%.1f%%', 100 * $info['excl_wt'] / $totals['wt'])
                         . ")\\n" . $info['ct'] . ' total calls"';
            } elseif (isset($left[$symbol], $right[$symbol])) {
                $label = ', label="' . addslashes($symbol) .
                         "\\nInc: " . sprintf('%.3f', $left[$symbol]['wt'] / 1000.0)
                         . ' ms - '
                         . sprintf('%.3f', $right[$symbol]['wt'] / 1000.0) . ' ms = '
                         . sprintf('%.3f', $info['wt'] / 1000.0) . ' ms' .
                         "\\nExcl: "
                         . sprintf('%.3f', $left[$symbol]['excl_wt'] / 1000.0)
                         . ' ms - ' . sprintf('%.3f', $right[$symbol]['excl_wt'] / 1000.0)
                         . ' ms = ' . sprintf('%.3f', $info['excl_wt'] / 1000.0) . ' ms' .
                         "\\nCalls: " . sprintf('%.3f', $left[$symbol]['ct']) . ' - '
                         . sprintf('%.3f', $right[$symbol]['ct']) . ' = '
                         . sprintf('%.3f', $info['ct']) . '"';
            } elseif (isset($left[$symbol])) {
                $label = ', label="' . addslashes($symbol) .
                         "\\nInc: " . sprintf('%.3f', $left[$symbol]['wt'] / 1000.0)
                         . ' ms - 0 ms = ' . sprintf('%.3f', $info['wt'] / 1000.0)
                         . ' ms' . "\\nExcl: "
                         . sprintf('%.3f', $left[$symbol]['excl_wt'] / 1000.0)
                         . ' ms - 0 ms = '
                         . sprintf('%.3f', $info['excl_wt'] / 1000.0) . ' ms' .
                         "\\nCalls: " . sprintf('%.3f', $left[$symbol]['ct']) . ' - 0 = '
                         . sprintf('%.3f', $info['ct']) . '"';
            } else {
                $label = ', label="' . addslashes($symbol) .
                         "\\nInc: 0 ms - "
                         . sprintf('%.3f', $right[$symbol]['wt'] / 1000.0)
                         . ' ms = ' . sprintf('%.3f', $info['wt'] / 1000.0) . ' ms' .
                         "\\nExcl: 0 ms - "
                         . sprintf('%.3f', $right[$symbol]['excl_wt'] / 1000.0)
                         . ' ms = ' . sprintf('%.3f', $info['excl_wt'] / 1000.0) . ' ms' .
                         "\\nCalls: 0 - " . sprintf('%.3f', $right[$symbol]['ct'])
                         . ' = ' . sprintf('%.3f', $info['ct']) . '"';
            }
            $result .= 'N' . $sym_table[$symbol]['id'];
            $result .= "[shape=$shape $label $width $height $fontsize $fillcolor];\n";
        }

        // Generate all the edges' information.
        foreach ($raw_data as $parent_child => $info) {
            list( $parent, $child ) = uprofiler_parse_parent_child($parent_child);

            if (isset($sym_table[$parent], $sym_table[$child]) && (
                    empty($this->func)
                    || (! empty($this->func) && ($parent == $this->func || $child == $this->func))
                )
            ) {
                $label = $info['ct'] == 1 ? $info['ct'] . ' call' : $info['ct'] . ' calls';

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

                if ($this->critical && isset($path_edges[uprofiler_build_parent_child_key($parent, $child)])) {
                    $linewidth  = 10;
                    $arrow_size = 2;
                }

                $result .= 'N' . $sym_table[$parent]['id'] . ' -> N' . $sym_table[$child]['id'];
                $result .= "[arrowsize=$arrow_size, color=grey, style=\"setlinewidth($linewidth)\","
                           . ' label="' . $label
                           . '", headlabel="' . $headlabel
                           . '", taillabel="' . $taillabel
                           . '" ]';
                $result .= ";\n";
            }
        }
        $result .= "\n}";

        return $result;
    }

    public function generate_image_by_dot($dot_script)
    {
        $descriptorspec = [
            0 => [ 'pipe', 'r' ],
            1 => [ 'pipe', 'w' ],
            2 => [ 'pipe', 'w' ]
        ];

        $cmd     = ' dot -T' . $this->type;
        $process = proc_open($cmd, $descriptorspec, $pipes, sys_get_temp_dir(), [ 'PATH' => getenv('PATH') ]);

        if (is_resource($process)) {
            fwrite($pipes[0], $dot_script);
            fclose($pipes[0]);

            $output = stream_get_contents($pipes[1]);
            $err    = stream_get_contents($pipes[2]);

            if (! empty($err)) {
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

    public function generate_mime_header($length)
    {
        switch ($this->type) {
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
                $mime = 'image/svg+xml';
                break;
            case 'ps':
                $mime = 'application/postscript';
                break;
            default:
                $mime = false;
        }

        if ($mime) {
            header("Content-type:$mime", true);
            header("Content-length:$length", true);
        }
    }

    public function get_children_table($raw_data)
    {
        $children_table = [ ];
        foreach ($raw_data as $parent_child => $info) {
            list( $parent, $child ) = uprofiler_parse_parent_child($parent_child);
            if (! isset($children_table[$parent])) {
                $children_table[$parent] = [ $child ];
            } else {
                $children_table[$parent][] = $child;
            }
        }
        return $children_table;
    }
}