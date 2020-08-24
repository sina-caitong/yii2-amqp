<?php

namespace pzr\amqp\cli\Communication;

use pzr\amqp\cli\helper\AmqpIni;

class CommunFactory
{

    private static $instance = null;

    public static function getInstance() {
        if (static::$instance) return static::$instance;
        $config = AmqpIni::readCommun();
        $class = $config['class'];
        unset($config['class']);
        $instance = new $class($config);
        if (!($instance instanceof CommunInterface)) return null;
        return static::$instance = $instance;
    }

}