<?php
namespace Rarst\Sideface\Run;

use DateTime;

class Run implements RunInterface
{
    protected $id;
    protected $source;
    protected $time;
    protected $data;

    /**
     * @param string $id
     * @param string $source
     * @param string $timestamp
     * @param array  $data
     */
    public function __construct($id, $source, $timestamp, $data)
    {
        $this->id     = $id;
        $this->source = $source;
        $this->time   = new \DateTimeImmutable('@'.$timestamp);
        $this->data   = $data;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return DateTime
     */
    public function getTime()
    {
        return $this->time;
    }
}
