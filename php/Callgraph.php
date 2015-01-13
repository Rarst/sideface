<?php
namespace Rarst\Sideface;

use iUprofilerRuns;

class Callgraph
{
    /**
     * @param object  $uprofiler_runs_impl An object that implements the iUprofilerRuns interface
     * @param integer $run_id              integer, the unique id for the phprof run, this is the
     *                                     primary key for phprof database table.
     * @param string  $type                string, one of the supported image types. See also
     *                                     $uprofiler_legal_image_types.
     * @param float   $threshold           the threshold value [0,1). The functions in the
     *                                     raw_data whose exclusive wall times ratio are below the
     *                                     threshold will be filtered out and won't appear in the
     *                                     generated image.
     * @param string  $func                the focus function.
     * @param string  $source
     * @param boolean $critical_path
     */
    public function render_image(
        iUprofilerRuns $uprofiler_runs_impl,
        $run_id,
        $type,
        $threshold,
        $func,
        $source,
        $critical_path
    ) {

        $content = uprofiler_get_content_by_run(
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
        $symbol_tab1     = uprofiler_compute_flat_info($raw_data1, $total1);
        $symbol_tab2     = uprofiler_compute_flat_info($raw_data2, $total2);
        $run_delta       = uprofiler_compute_diff($raw_data1, $raw_data2);
        $script          = uprofiler_generate_dot_script(
            $run_delta,
            $threshold,
            $source,
            null,
            null,
            true,
            $symbol_tab1,
            $symbol_tab2
        );
        $content         = uprofiler_generate_image_by_dot($script, $type);

        uprofiler_generate_mime_header($type, strlen($content));
        echo $content;
    }
}
