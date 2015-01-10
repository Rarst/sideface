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
        init_metrics($uprofiler_data, $rep_symbol, $sort, $diff_report);
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
        profiler_report($url_params, $rep_symbol, $sort, $run1, $run1_desc, $run1_data);

        $this->body = ob_get_clean();
    }
}
