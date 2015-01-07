<?php

/**
 * iUprofilerRuns interface for getting/saving a uprofiler run.
 *
 * Clients can either use the default implementation,
 * namely UprofilerRuns_Default, of this interface or define
 * their own implementation.
 *
 * @author Kannan
 */
interface iUprofilerRuns
{
    /**
     * Returns uprofiler data given a run id ($run) of a given
     * type ($type).
     *
     * Also, a brief description of the run is returned via the
     * $run_desc out parameter.
     */
    public function get_run($run_id, $type, &$run_desc);

    /**
     * Save uprofiler data for a profiler run of specified type
     * ($type).
     *
     * The caller may optionally pass in run_id (which they
     * promise to be unique). If a run_id is not passed in,
     * the implementation of this method must generated a
     * unique run id for this saved uprofiler run.
     *
     * Returns the run id for the saved uprofiler run.
     *
     */
    public function save_run($uprofiler_data, $type, $run_id = null);
}
