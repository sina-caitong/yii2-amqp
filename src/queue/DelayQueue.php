<?php


namespace pzr\amqp\queue;

use PhpAmqpLib\Wire\AMQPTable;
use pzr\amqp\ExchangeType;
use pzr\amqp\MyAmqp;

/**
 * 队列具体实现
 */
class DelayQueue extends MyAmqp
{

    /**
     * 延时队列的名称
     *
     * @var string
     */
    public $delayQueueName;
    /**
     * 延时队列的路由名称
     *
     * @var string
     */
    public $delayExchangeName;
    /**
     * 延时队列的路由键
     *
     * @var string
     */
    public $delayRoutingKey = '';
    /**
     * 延时时间（单位：毫秒）
     * 
     * @var integer
     */
    public $ttl = 0;

    public function bind()
    {
        // 防止队列被篡改
        if ($this->isQueueCreated()) {
            return true;
        }
        $this->open();
        $queueName = $this->queueName;
        $exchangeName = $this->exchangeName;

        //{{ 绑定备份队列
        $aeExchange =  $exchangeName . '_AE';
        $aeQueue =  $queueName . '_AE';
        $aeArguments = [
            'alternate-exchange' => $aeExchange,
        ];
        $this->queueDeclare($aeQueue);
        $this->exchangeDeclare($aeExchange, ExchangeType::FANOUT);
        $this->queueBind($aeQueue, $aeExchange);
        //}}

        //{{ 绑定延时队列
        $this->queueDeclare($this->delayQueueName);
        $this->exchangeDeclare($this->delayExchangeName, ExchangeType::DIRECT);
        $this->queueBind($this->delayQueueName, $this->delayExchangeName);
        //}}

        //{{ 绑定正常队列
        $arguments = [
            'x-dead-letter-exchange' => $this->delayExchangeName,
            "x-dead-letter-routing-key" => $this->delayRoutingKey,  //死信队列的路由
            "x-message-ttl" => $this->ttl,             //消息的有效时间
        ];
        $this->queuesDeclare($queueName, $arguments);
        $this->exchangeDeclare($exchangeName, $this->exchangeType, $aeArguments);
        $this->queuesBind($queueName, $exchangeName, $this->routingKey);
        //}}
    }

}
