<?php


namespace pzr\amqp\exception;

use Exception;

class UnknowException extends Exception
{

    public static function getName() {
        return 'unkonw exception about amqp';
    }

}
