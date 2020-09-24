<?php

use pzr\amqp\cli\command\Dispatcher;
use pzr\amqp\cli\command\SwooleDispatcher;

$baseDir = require dirname(__DIR__, 3) . '/FindVendor.php';
require $baseDir . '/vendor/autoload.php';
require $baseDir . '/vendor/yiisoft/yii2/Yii.php';

$dispatch = new SwooleDispatcher();
$dispatch->run();