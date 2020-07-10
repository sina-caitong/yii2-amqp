<?php


namespace pzr\amqp\exception;

use Exception;
use ReflectionParameter;

class MissPropertyException extends Exception
{

    public static function getName() {
        return 'Missing necessary parameters';
    }

}
