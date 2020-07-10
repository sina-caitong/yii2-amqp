<?php

namespace pzr\amqp\event;

use yii\base\Event;

class PushEvent extends Event
{
    /**
     * @var string AMQPMessage->delivery_info['message_id']
     */
    public $id;

    /**
     * @var \pzr\amqp\JobInterface
     */
    public $job;

    /**
     * @var array
     */
    public $jobs;

    /**
     * @var string
     */
    public $exchangeName;

    /**
     * @var string
     */
    public $routingKey;

    /**
     * 客户端发送消息确认机制
     * false 默认客户端启用消息确认机制
     * true 客户端关闭消息确认机制
     * 
     * @var bool
     */
    public $noWait = false;

}