<?php

namespace pzr\amqp;

use PhpAmqpLib\Message\AMQPMessage;
use pzr\amqp\event\ExecEvent;
use pzr\amqp\event\PushEvent;
use yii\base\Behavior;

class LogBehavior extends Behavior
{

    /**
     * @var AmqpBase
     * @inheritdoc
     */
    public $owner;

    public function events()
    {
        return [
            // AmqpBase::EVENT_BEFORE_PUSH => 'beforePush',
            // AmqpBase::EVENT_AFTER_PUSH => 'afterPush',
            AmqpBase::EVENT_PUSH_ACK => 'pushAck',
            AmqpBase::EVENT_PUSH_NACK => 'pushNack',
            // AmqpBase::EVENT_BEFORE_EXEC => 'beforeExec',
            // AmqpBase::EVENT_AFTER_EXEC => 'afterExec',
        ];
    }

    /**
     * @param PushEvent $event
     * @return void
     */
    public function beforePush(PushEvent $event)
    {
        
    }

    /**
     * @param PushEvent $event
     * @return void
     */
    public function afterPush(PushEvent $event)
    {
    }

    /**
     * 消息推送成功应答
     * 
     * @param PushEvent $event
     * @return void
     */
    public function pushAck(PushEvent $event)
    {
        error_log("ack \n", 3, '/Users/pzr/test.log');
    }

    /**
     * 消息推送失败应答
     * 
     * @param PushEvent $event
     * @return void
     */
    public function pushNack(PushEvent $event)
    {
        error_log("nack \n", 3, '/Users/pzr/test.log');
    }

    public function beforeExec(ExecEvent $event)
    {
    }

    public function afterExec(ExecEvent $event)
    {
    }
}
