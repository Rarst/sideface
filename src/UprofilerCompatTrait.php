<?php

namespace Rarst\Sideface;

/**
 * Implements compatibility layer with iUprofilerRuns interface.
 */
trait UprofilerCompatTrait
{
    /**
     * @param string $runId
     * @param string $source
     *
     * @return RunInterface
     */
    abstract public function getRun($runId, $source);

    /**
     * @deprecated
     *
     * @param $run_id
     * @param $type
     * @param $run_desc
     *
     * @return array|bool
     */
    public function get_run($run_id, $type, &$run_desc)
    {
        $run = $this->getRun($run_id, $type);
        if (empty( $run )) {
            $run_desc = "Invalid Run Id = $run_id";
            return null;
        }
        $run_desc = "uprofiler Run (Namespace=$type)";
        return $run->getData();
    }

    abstract public function saveRun($data, $source, $runId = null);

    /**
     * @deprecated
     *
     * @param      $uprofiler_data
     * @param      $type
     * @param null $run_id
     *
     * @return string|bool
     */
    public function save_run($uprofiler_data, $type, $run_id = null)
    {
        return $this->saveRun($uprofiler_data, $type, $run_id);
    }
}
