<?php

namespace Rarst\Sideface\Run;

use DateTime;

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

    /**
     * @return DateTime
     */
    public function getTime();
}
