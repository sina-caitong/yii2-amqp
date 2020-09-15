<?php

namespace pzr\amqp\cli\handler;

use pzr\amqp\cli\helper\AmqpIniHelper;
use pzr\amqp\exception\InvalidArgumentException;

class HandlerFactory
{
    private static $handlerObjects = array();

    /** @return BaseHandler */
    public static function getHandler()
    {
        $handler = AmqpIniHelper::readHandler();
        $class = $handler['class'];
        if (isset(self::$handlerObjects[$class]) && $object = self::$handlerObjects[$class]) return $object;
        unset($handler['class']);
        $object = new $class($handler);
        if ($object instanceof HandlerInterface) {
            return self::$handlerObjects[$class] = $object;
        }

        throw new InvalidArgumentException('invalid config of class');
    }
}
