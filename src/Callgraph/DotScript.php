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
        $t                = $this;

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
        foreach ($sym_table as $symbol => $i) {
            if ($i['excl_wt'] == 0) {
                $sizing_factor = $max_sizing_ratio;
            } else {
                $sizing_factor = $max_wt / abs($i['excl_wt']);
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

            $shape = $symbol === 'main()' ? 'octagon' : 'box';

            $l = $left[$symbol] ?? [];
            $r = $right[$symbol] ?? [];

            if (! $l) {
                $label = ', label="'
                         . addslashes($symbol) . '\n'
                         . '● ' . $t->ms($i['wt']) . ' (' . $t->pc($i['wt'], $totals['wt']) . ')\n'
                         . '○ ' . $t->ms($i['excl_wt']) . ' (' . $t->pc($i['excl_wt'], $totals['wt']) . ')\n'
                         . $i['ct'] . ' calls"';
            } elseif ($l && $r) {
                $label = ', label="' . addslashes($symbol) . '\n'
                         . '● ' . $t->sub($l['wt'], $r['wt'], $i['wt'])
                         . '○ ' . $t->sub($l['excl_wt'], $r['excl_wt'], $i['excl_wt'])
                         . 'Calls: ' . $l['ct'] . ' - ' . $r['ct'] . ' = ' . $i['ct'] . '"';
            } elseif ($l) {
                $label = ', label="' . addslashes($symbol) . '\n'
                         . '● ' . $t->sub($l['wt'], 0, $i['wt'])
                         . '○ ' . $t->sub($l['excl_wt'], 0, $i['excl_wt'])
                         . 'Calls: ' . $l['ct'] . ' - 0 = ' . $i['ct'] . '"';
            } else {
                $label = ', label="' . addslashes($symbol) . '\n'
                         . '● ' . $t->sub(0, $r['wt'], $i['wt'])
                         . '○ ' . $t->sub(0, $r['excl_wt'], $i['excl_wt'])
                         . 'Calls: 0 - ' . $r['ct'] . ' = ' . $i['ct'] . '"';
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
        $result .= "\n" . '}';

        return $result;
    }

    private function sub($left, $right, $result): string
    {
        return sprintf('%s - %s = %s\n', $this->ms($left), $this->ms($right), $this->ms($result));
    }

    private function ms($microseconds): string
    {
        if (0 === $microseconds) {
            return '0 ms';
        }
        $milliseconds = $microseconds / 1000.0;
        $format       = (abs($milliseconds) > 1) ? '%.1f' : '%.3f';

        return sprintf($format, $milliseconds) . ' ms';
    }

    private function pc($part, $total)
    {
        return sprintf('%.1f%%', 100 * $part / $total);
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
