<?php

namespace Rarst\Sideface;

/**
 * Implements compatibility layer with iUprofilerRuns interface.
 */
trait UprofilerCompatTrait
{
    public function getRun($runId, $source)
    {
        return false;
    }

    public function get_run($run_id, $type, &$run_desc)
    {
        $run = $this->getRun($run_id, $type);
        if (empty( $run )) {
            $run_desc = "Invalid Run Id = $run_id";
            return null;
        }
        $run_desc = "uprofiler Run (Namespace=$type)";
        return $run;
    }

    public function saveRun($data, $source, $runId = null)
    {
        return false;
    }

    public function save_run($uprofiler_data, $type, $run_id = null)
    {
        return $this->saveRun($uprofiler_data, $type, $run_id);
    }
}
