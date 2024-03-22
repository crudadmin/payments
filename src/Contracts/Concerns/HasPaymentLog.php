<?php

namespace AdminPayments\Contracts\Concerns;

use Exception;
use AdminPayments\Contracts\Exceptions\PaymentException;

trait HasPaymentLog
{
    public function logReport($type, $code, $message = null, $log = null, callable $callback = null)
    {
        $row = $this->log()->getModel();

        $row->fill([
            'row_id' => $this->getKey(),
            'table' => $this->getTable(),
            'type' => $type,
            'code' => $code,
            'message' => substr($this->toLogResponse($message), 0, 255),
            'log' => $this->toLogResponse($log),
        ]);

        if ( is_callable($callback) ){
            $callback($row);
        }

        $row->save();

        return $row;
    }

    public function logException(Exception $e, callable $callback = null)
    {
        $code = $e->getCode();

        if ( $e instanceof PaymentException ) {
            $message = $e->getMessage();
            $log = $e->getLog();
        } else {
            $log = $e->getMessage();
        }

        return $this->logReport('error', $code, $message ?? null, $log ?? null, $callback);
    }

    private function toLogResponse($response)
    {
        if ( is_array($response) ){
            return json_encode($response, JSON_PRETTY_PRINT);
        }

        return $response;
    }
}