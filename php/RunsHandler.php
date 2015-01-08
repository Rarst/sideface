<?php
namespace Rarst\Sideface;

use iUprofilerRuns;

class RunsHandler implements iUprofilerRuns
{
    use UprofilerCompatTrait;

    protected $dir = '';
    protected $suffix = '';

    /**
     * @param null|string $dir path to saved runs
     * @param string      $suffix
     */
    public function __construct($dir = null, $suffix = 'uprofiler')
    {
        $this->suffix = $suffix;
        if (empty( $dir )) {
            $dir = ini_get($suffix . '.output_dir');
        }
        if (empty( $dir )) {
            $dir = sys_get_temp_dir();
        }
        $this->dir = $dir;
    }

    /**
     * @param string $runId
     * @param string $source
     *
     * @return string
     */
    protected function getFileName($runId, $source)
    {
        $file = "{$runId}.{$source}.{$this->suffix}";
        if (! empty( $this->dir )) {
            $file = $this->dir . '/' . $file;
        }
        return $file;
    }

    /**
     * @param string $runId
     * @param string $source
     *
     * @return array|bool
     */
    public function getRun($runId, $source)
    {
        $fileName = $this->getFileName($runId, $source);
        if (! file_exists($fileName)) {
            return false;
        }
        $contents = file_get_contents($fileName);
        return unserialize($contents);
    }

    /**
     * @param string      $data
     * @param string      $source
     * @param null|string $runId
     *
     * @return string|bool
     */
    public function saveRun($data, $source, $runId = null)
    {
        $data = serialize($data);
        if ($runId === null) {
            $runId = uniqid();
        }
        $fileName = $this->getFileName($runId, $source);
        $result   = file_put_contents($fileName, $data);
        if (false === $result) {
            return false;
        }
        return $runId;
    }

    public function getRunsList()
    {
        $runs = [ ];
        if (! is_dir($this->dir)) {
            return $runs;
        }
        $files = glob("{$this->dir}/*.{$this->suffix}");
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        foreach ($files as $file) {
            list( $run, $source ) = explode('.', basename($file, '.' . $this->suffix), 2);
            $runs[] = [
                'id'     => $run,
                'source' => $source,
                'suffix' => $this->suffix,
                'time'   => filemtime($file),
            ];
        }
        return $runs;
    }
}
