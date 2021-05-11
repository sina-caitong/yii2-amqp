<?php

namespace pzr\amqp\event;

use yii\base\Event;

class ExecEvent extends Event
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
     * @var \PhpAmqpLib\Message\AMQPMessage
     */
    public $message;

    /**
     * @var string
     */
    public $queueName;

    /**
     * 任务执行的结果
     * 
     * @var boolean
     */
    public $result;

    public $error = null;

    /**
     * 消息体
     *
     * @var AMQPMessage
     */
    public $payload;

}