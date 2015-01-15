<?php

namespace Rarst\Sideface;

interface RunInterface
{
    /**
     * @return string
     */
    public function getId();

    /**
     * @return string
     */
    public function getSource();

    /**
     * @return array
     */
    public function getData();
}
