<?php

namespace pzr\amqp\cli\communication;

use pzr\amqp\cli\helper\AmqpIniHelper;

class CommunFactory
{

    private static $instance = null;

    public static function getInstance() {
        if (static::$instance) return static::$instance;
        $config = AmqpIniHelper::readCommun();
        $class = $config['class'];
        unset($config['class']);
        $instance = new $class($config);
        if (!($instance instanceof CommunInterface)) return null;
        return static::$instance = $instance;
    }

}