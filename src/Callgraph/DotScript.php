<?php
declare(strict_types=1);

namespace Rarst\Sideface\Callgraph;

use Rarst\Sideface\Run\RunData;

class DotScript
{
    /** @var float */
    private $threshold;

    /** @var bool */
    private $critical;

    /** @var string */
    private $func;

    public function __construct(float $threshold, bool $critical, string $func)
    {
        $this->threshold = $threshold;
        $this->critical  = $critical;
        $this->func      = $func;
    }

    public function getScript($raw_data, $right = null, $left = null): string
    {
        $max_width        = 5;
        $max_height       = 3.5;
        $max_fontsize     = 35;
        $max_sizing_ratio = 20;
        $runDataObject    = new RunData($raw_data);
        $sym_table        = $runDataObject->getFlat();
        $totals           = $runDataObject->getTotals();

        if ($this->critical) {
            [$path, $path_edges] = $this->getCriticalPath($raw_data);
        }

        if ($this->func) {
            $sym_table = array_intersect_key($sym_table, $this->getRelatedToFunction($raw_data));
        } else {
            $sym_table = array_intersect_key($sym_table, $this->getAboveThreshold($sym_table, $totals['wt']));
        }

        $max_wt = max(array_map('abs', array_column($sym_table, 'excl_wt')));

        $cur_id = 0;
        foreach ($sym_table as $symbol => $info) {
            $sym_table[$symbol]['id'] = $cur_id++;
        }

        $result = "digraph call_graph {\n";

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
            $fillcolor = (($sizing_factor < 1.5) ? ', style=filled, fillcolor=red' : '');

            // highlight nodes along critical path.
            if ($this->critical && ! $fillcolor && array_key_exists($symbol, $path)) {
                $fillcolor = ', style=filled, fillcolor=yellow';
            }

            $fontsize = ', fontsize=' . (int)($max_fontsize / (($sizing_factor - 1) / 10 + 1));
            $width    = ', width=' . sprintf('%.1f', $max_width / $sizing_factor);
            $height   = ', height=' . sprintf('%.1f', $max_height / $sizing_factor);

            if ($symbol === 'main()') {
                $shape = 'octagon';
                $name  = 'Total: ' . ($totals['wt'] / 1000.0) . " ms\\n";
                $name  .= addslashes($symbol);
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
            [$parent, $child] = uprofiler_parse_parent_child($parent_child);

            if (isset($sym_table[$parent], $sym_table[$child]) && (
                    empty($this->func)
                    || (! empty($this->func) && ($parent == $this->func || $child == $this->func))
                )
            ) {
                $label = $info['ct'] == 1 ? $info['ct'] . ' call' : $info['ct'] . ' calls';

                $headlabel = $sym_table[$child]['wt'] > 0
                    ? sprintf('%.1f%%', 100 * $info['wt'] / $sym_table[$child]['wt'])
                    : '0.0%';

                $taillabel = ($sym_table[$parent]['wt'] > 0)
                    ? sprintf(
                        '%.1f%%',
                        100 * $info['wt'] / ($sym_table[$parent]['wt'] - $sym_table[$parent]['excl_wt'])
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

    private function getCriticalPath(array $data): array
    {
        $children_table = $this->getChildrenTable($data);
        $node           = 'main()';
        $path           = [];
        $path_edges     = [];
        $visited        = [];

        while ($node) {
            $visited[$node] = true;

            if (isset($children_table[$node])) {
                $max_child = null;

                foreach ($children_table[$node] as $child) {
                    if (isset($visited[$child])) {
                        continue;
                    }

                    if ($max_child === null ||
                        abs($data[uprofiler_build_parent_child_key($node, $child)]['wt']) >
                        abs($data[uprofiler_build_parent_child_key($node, $max_child)]['wt'])
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

        return [$path, $path_edges];
    }

    private function getRelatedToFunction($raw_data): array
    {
        $related = [];

        foreach (array_keys($raw_data) as $parent_child) {
            [$parent, $child] = uprofiler_parse_parent_child($parent_child);

            if ($parent === $this->func || $child === $this->func) {
                $related[$parent] = true;
                $related[$child]  = true;
            }
        }

        return $related;
    }

    private function getAboveThreshold($sym_table, $total_wt): array
    {
        $above = [];

        foreach ($sym_table as $symbol => $info) {
            if (abs($info['wt'] / $total_wt) > $this->threshold) {
                $above[$symbol] = true;
            }
        }

        return $above;
    }

    private function getChildrenTable($raw_data): array
    {
        $children_table = [];
        foreach ($raw_data as $parent_child => $info) {
            [$parent, $child] = uprofiler_parse_parent_child($parent_child);
            if (! isset($children_table[$parent])) {
                $children_table[$parent] = [$child];
            } else {
                $children_table[$parent][] = $child;
            }
        }
        return $children_table;
    }
}
