<?php

use pzr\amqp\cli\connect\SocketConnection;
use pzr\amqp\cli\connect\SwooleConnection;
use pzr\amqp\cli\helper\AmqpIniHelper;
use pzr\amqp\cli\Server;

$baseDir = require dirname(__DIR__, 3) . '/FindVendor.php';
require $baseDir . '/vendor/autoload.php';
require $baseDir . '/vendor/yiisoft/yii2/Yii.php';


$unixPath = AmqpIniHelper::getUnix();
@unlink($unixPath);

$socket = new SocketConnection($unixPath);
// $socket = new SwooleConnection($unixPath);

chmod($unixPath, 0777);
$socket->start();