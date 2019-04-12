<?php
namespace Rarst\Sideface\Callgraph;

use Rarst\Sideface\Run\RunData;
use Rarst\Sideface\Run\RunInterface;

class Callgraph
{
    protected $legal_image_types = [ 'jpg', 'gif', 'png', 'svg', 'ps' ];

    /** @var string */
    protected $type = 'svg';

    /** @var DotScript */
    private $dotScript;

    public function __construct(array $args = [ ])
    {
        if (in_array($args['type'] ?? 'svg', $this->legal_image_types, true)) {
            $this->type = $args['type'];
        }

        $this->dotScript = new DotScript(
            $args['threshold'] ?? 0.01,
            $args['critical'] ?? true,
            $args['func'] ?? ''
        );
    }

    public function render_image(RunInterface $run)
    {
        $script = $this->generate_dot_script($run->getData());

        return $this->generate_image_by_dot($script);
    }

    public function render_diff_image(RunInterface $run1, RunInterface $run2)
    {
        $raw_data1      = $run1->getData();
        $raw_data2      = $run2->getData();
        $runDataObject1 = new RunData($raw_data1);
        $symbol_tab1    = $runDataObject1->getFlat();
        $runDataObject2 = new RunData($raw_data2);
        $symbol_tab2    = $runDataObject2->getFlat();
        $run_delta      = $runDataObject1->diffTo($raw_data2);
        $script         = $this->generate_dot_script($run_delta, $symbol_tab1, $symbol_tab2);

        return $this->generate_image_by_dot($script);
    }

    public function generate_dot_script($raw_data, $right = null, $left = null): string
    {
        return $this->dotScript->getScript($raw_data, $right, $left);
    }

    public function generate_image_by_dot($dot_script)
    {
        $descriptorspec = [
            0 => [ 'pipe', 'r' ],
            1 => [ 'pipe', 'w' ],
            2 => [ 'pipe', 'w' ]
        ];

        $cmd     = ' dot -T' . $this->type;
        $process = proc_open($cmd, $descriptorspec, $pipes, sys_get_temp_dir(), [ 'PATH' => getenv('PATH') ]);

        if (is_resource($process)) {
            fwrite($pipes[0], $dot_script);
            fclose($pipes[0]);

            $output = stream_get_contents($pipes[1]);
            $err    = stream_get_contents($pipes[2]);

            if (! empty($err)) {
                print "failed to execute cmd: \"$cmd\". stderr: `$err'\n";
                exit;
            }

            fclose($pipes[2]);
            fclose($pipes[1]);
            proc_close($process);

            return $output;
        }
        print "failed to execute cmd \"$cmd\"";
        exit();
    }
}
