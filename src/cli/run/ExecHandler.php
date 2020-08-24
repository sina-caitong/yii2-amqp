<?php

use pzr\amqp\cli\handler\HandlerFactory;

$baseDir = require dirname(__DIR__, 3) . '/FindVendor.php';
require $baseDir . '/vendor/autoload.php';
require $baseDir . '/vendor/yiisoft/yii2/Yii.php';

$exec = new ExecHandler();
$exec->run();

class ExecHandler
{

    private $handler;

    public function __construct()
    {
        $this->handler = HandlerFactory::getHandler();
    }

    public function run() {
        $this->handler->handle();
    }

}

