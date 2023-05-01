<?php

namespace AdminPayments\Gateways\Exceptions;

use Exception;

class PaymentException extends Exception
{
    /**
     * Error log
     *
     * @var  null
     */
    public $log = null;

    public function __construct($message = null, $code = null, $logOrResponse = null)
    {
        $this->message = $message;

        $this->code = $code ?: $this->code;

        $this->log = $logOrResponse;
    }

    /**
     * Set order log
     *
     * @param  array|string  $log
     */
    public function setLog($log)
    {
        $this->log = $log;

        return $this;
    }

    /**
     * Set error request response into log
     *
     * @param  array|string  $log
     */
    public function setResponse($log)
    {
        $this->log = $log;

        return $this;
    }

    public function getLog()
    {
        return $this->log;
    }

    /**
     * Set order erro code
     *
     * @param  string  $code
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }
}