<?php

use pzr\amqp\cli\Dispatcher;


$baseDir = require dirname(__DIR__, 3) . '/FindVendor.php';
require $baseDir . '/vendor/autoload.php';
require $baseDir . '/vendor/yiisoft/yii2/Yii.php';

$dispatch = new Dispatcher();
$dispatch->byQueues();