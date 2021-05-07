<?php


use pzr\amqp\jobs\RpcJob;
use pzr\amqp\MyYii;
use pzr\amqp\Response;

$basePath = __DIR__ . '/../../';

require $basePath . '/vendor/autoload.php';
define('YII_CONSOLE_PATH', $basePath . 'frontend/config/console.php');
$yii = new MyYii();

Yii::$app->easyQueue->on(AmqpBase::EVENT_BEFORE_PUSH, function ($event) {
    Yii::$app->easyQueue->bind(); //绑定队列，如果已经绑定可以注释此方法
    // $event->noWait = true; //关闭客户端的消息确认机制
});

Yii::$app->easyQueue->push(new CountJob([
    'count' => 1,
]);