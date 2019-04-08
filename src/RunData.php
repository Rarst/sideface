<?php
namespace Rarst\Sideface;

class RunData implements RunDataInterface
{
    protected $data = [ ];
    protected $flat = [ ];
    protected $totals = [ ];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function getFlat()
    {
        if (! empty($this->flat)) {
            return $this->flat;
        }

        $metrics = $this->getMetrics($this->data);

        $this->totals = [
            'ct'      => 0,
            'wt'      => 0,
            'ut'      => 0,
            'st'      => 0,
            'cpu'     => 0,
            'mu'      => 0,
            'pmu'     => 0,
            'samples' => 0
        ];

        $this->flat = $this->getInclusive();

        foreach ($metrics as $metric) {
            $this->totals[$metric] = $this->flat['main()'][$metric];
        }

        foreach ($this->flat as $symbol => $info) {
            foreach ($metrics as $metric) {
                $this->flat[$symbol]['excl_' . $metric] = $this->flat[$symbol][$metric];
            }
            $this->totals['ct'] += $info['ct'];
        }

        foreach ($this->data as $parent_child => $info) {
            list( $parent ) = uprofiler_parse_parent_child($parent_child);

            if ($parent) {
                foreach ($metrics as $metric) {
                    // make sure the parent exists hasn't been pruned.
                    if (isset($this->flat[$parent])) {
                        $this->flat[$parent]['excl_' . $metric] -= $info[$metric];
                    }
                }
            }
        }

        return $this->flat;
    }

    public function getTotals()
    {
        if (empty($this->totals)) {
            $this->getFlat();
        }

        return $this->totals;
    }

    public function getInclusive()
    {
        $metrics    = $this->getMetrics($this->data);
        $symbol_tab = [ ];

        foreach ($this->data as $parent_child => $info) {
            list( $parent, $child ) = uprofiler_parse_parent_child($parent_child);

            if (! isset($symbol_tab[$child])) {
                $symbol_tab[$child] = [ 'ct' => $info['ct'] ];

                foreach ($metrics as $metric) {
                    $symbol_tab[$child][$metric] = $info[$metric];
                }
            } else {
                $symbol_tab[$child]['ct'] += $info['ct'];

                foreach ($metrics as $metric) {
                    $symbol_tab[$child][$metric] += $info[$metric];
                }
            }
        }

        return $symbol_tab;
    }

    public function diffTo(array $data)
    {
        $metrics = $this->getMetrics($data);
        $delta   = $data;

        foreach ($this->data as $parent_child => $info) {
            if (! isset($delta[$parent_child])) {
                $delta[$parent_child] = [ 'ct' => 0 ];

                foreach ($metrics as $metric) {
                    $delta[$parent_child][$metric] = 0;
                }
            }

            $delta[$parent_child]['ct'] -= $info['ct'];

            foreach ($metrics as $metric) {
                $delta[$parent_child][$metric] -= $info[$metric];
            }
        }

        return $delta;
    }

    public function getMetrics(array $data)
    {
        $possible_metrics = uprofiler_get_possible_metrics();
        $metrics          = [ ];

        foreach ($possible_metrics as $metric => $desc) {
            if (isset($data['main()'][$metric])) {
                $metrics[] = $metric;
            }
        }

        return $metrics;
    }
}
