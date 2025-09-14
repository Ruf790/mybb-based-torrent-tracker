<?php
/*
 * @ https://EasyToYou.eu - IonCube v11 Decoder Online
 * @ PHP 7.2 & 7.3
 * @ Decoder version: 1.0.6
 * @ Release: 10/08/2022
 */

class ScraperException extends Exception
{
    private $connectionerror = NULL;
    public function __construct($message, $code = 0, $connectionerror = false)
    {
        $this->connectionerror = $connectionerror;
        parent::__construct($message, $code);
    }
    public function isConnectionError()
    {
        return $this->connectionerror;
    }
}
abstract class tscraper
{
    protected $timeout = NULL;
    public function __construct($timeout = 2)
    {
        $this->timeout = $timeout;
    }
}

?>