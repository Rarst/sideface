<?php
namespace Rarst\Sideface;

class Run implements RunInterface
{
    protected $id;
    protected $source;
    protected $data;

    /**
     * @param string $id
     * @param string $source
     * @param array  $data
     */
    public function __construct($id, $source, $data)
    {
        $this->id     = $id;
        $this->source = $source;
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
}
