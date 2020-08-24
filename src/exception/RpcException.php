<?php


namespace pzr\amqp\exception;

use Exception;

class RpcException extends Exception
{

    public static function getName() {
        return 'rpc error';
    }

}
