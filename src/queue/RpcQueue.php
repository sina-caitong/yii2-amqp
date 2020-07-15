<?php

namespace pzr\amqp\queue;

use pzr\amqp\RpcAmqp;

class RpcQueue extends RpcAmqp
{

    public function bind() {
        $this->open();
        $queueName = $this->queueName;
        $exchangeName = $this->exchangeName;

        //{{ 绑定备份队列
        $aeExchange =  $exchangeName.'_AE';
        $aeQueue =  $queueName.'_AE';
        $aeArguments = [
            'alternate-exchange' => $aeExchange,
        ];
        $this->queueDeclare($aeQueue);
        $this->exchangeDeclare($aeExchange, 'fanout');
        $this->queueBind($aeQueue, $aeExchange);
        //}}

        //{{ 绑定正常队列
        $this->queuesDeclare($queueName);
        $this->exchangeDeclare($exchangeName, $this->exchangeType, $aeArguments);
        $this->queuesBind($queueName, $exchangeName, $this->routingKey);
        //}}
    }
    

}