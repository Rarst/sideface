<?php

namespace Rarst\Sideface\Run;

interface RunDataInterface
{
    /**
     * @return array
     */
    public function getFlat();

    /**
     * @return array
     */
    public function getTotals();

    /**
     * @return array
     */
    public function getInclusive();

    /**
     * @param array $data
     *
     * @return array
     */
    public function diffTo(array $data);
}
