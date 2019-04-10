<?php
declare(strict_types=1);

namespace Rarst\Sideface\Run;

use Rarst\Sideface\Report;
use Rarst\Sideface\RunInterface;
use Rarst\Sideface\RunsHandler;

class RunDomainLogic
{
    /** @var RunsHandler */
    private $handler;

    public function __construct(RunsHandler $handler)
    {
        $this->handler = $handler;
    }

    public function getRuns(string $source = null): array
    {
        $runs = $this->handler->getRunsList();

        if ($source) {
            $runs = array_filter($runs, static function ($run) use ($source) {
                /** @var RunInterface $run */
                return $run->getSource() === $source;
            });
        }

        return $runs;
    }

    public function getRunReport(string $runId, string $source, string $symbol = null): string
    {
        //    global $wts;
        // TODO aggregate runs stuff
        // run may be a single run or a comma separate list of runs
        // that'll be aggregated. If "wts" (a comma separated list
        // of integral weights is specified), the runs will be
        // aggregated in that ratio.
        //
        //    $runs_array = explode(',', $runId);
        //    if (count($runs_array) == 1) {
        //        $runData = $run->getData();
        //    } else {
        //        if (! empty( $wts )) {
        //            $wts_array = explode(",", $wts);
        //        } else {
        //            $wts_array = null;
        //        }
        //        $data    = $report->aggregate_runs($runsHandler, $runs_array, $wts_array, $source, false);
        //        $runData = $data['raw'];
        //    }

        $run    = $this->handler->getRun($runId, $source);
        $report = new Report(['source' => $source, 'run' => $runId]);

        $report->profilerReport($run, $symbol);

        return $report->getBody();
    }

    public function getDiffReport(string $runId1, string $runId2, string $source, string $symbol = null): string
    {
        $run1   = $this->handler->getRun($runId1, $source);
        $run2   = $this->handler->getRun($runId2, $source);
        $report = new Report(['source' => $source, 'run' => $runId1 . '–' . $runId2]);

        $report->profilerDiffReport($run1, $run2, $symbol);

        return $report->getBody();
    }
}
