<?php
declare(strict_types=1);

namespace Rarst\Sideface\Callgraph;

use Rarst\Sideface\Callgraph;
use Rarst\Sideface\RunsHandler;

class CallgraphDomainLogic
{
    /** @var RunsHandler */
    private $handler;

    public function __construct(RunsHandler $handler)
    {
        $this->handler = $handler;
    }

    public function getImage(string $runId, string $source, string $type = 'svg')
    {
        $run       = $this->handler->getRun($runId, $source);
        $callgraph = new Callgraph(['type' => $type]);

        ob_start();
        $callgraph->render_image($run);

        return ob_get_clean();
    }

    public function getDiffImage(string $runId1, string $runId2, string $source, string $type = 'svg')
    {
        $run1      = $this->handler->getRun($runId1, $source);
        $run2      = $this->handler->getRun($runId2, $source);
        $callgraph = new Callgraph(['type' => $type]);

        ob_start();
        $callgraph->render_diff_image($run1, $run2);

        return ob_get_clean();
    }
}
