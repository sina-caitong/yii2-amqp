<?php

return array(
    /** RPC消费者 */
    'rpcConsumer' => [
        'class' =>  \pzr\amqp\RpcAmqp::class,
        'host' => '127.0.0.1',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
    ],
    /** 普通消费者 */
    'consumer' => [
        // 'class' =>  \pzr\amqp\MyAmqp::class,
        'class' =>  \pzr\amqp\AmqpBase::class,
        'host' => '127.0.0.1',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
    ],
    /** 普通队列定义 */
    'easyQueue' => [
        'class' =>  \pzr\amqp\queue\EasyQueue::class,
        // 'as log' => \pzr\amqp\LogBehavior::class,
        'host' => '127.0.0.1',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
        'queueName' => 'easy_queue',
        'exchangeName' => 'easy_exchange',
        'duplicate' => 2, // 启动队列副本
        'routingKey' => 'easy',
        // Other driver options
    ],
    /** 延时队列定义 */
    'delayQueue' => [
        'class' =>  \pzr\amqp\queue\DelayQueue::class,
        'as log' => \pzr\amqp\LogBehavior::class,
        'host' => '127.0.0.1',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
        'queueName' => 'normal_queue',
        'exchangeName' => 'normal_exchange',
        'routingKey' => 'normal',
        'delayQueueName' => 'delay_queue',
        'delayExchangeName' => 'delay_exchange',
        'delayRoutingKey' => 'delay',
        'ttl' => 5000, //ms
        'duplicate' => 2,
        // Other driver options
    ],
    /** 策略API */
    'policy' => [
        'class' =>  \pzr\amqp\api\Policy::class,
        'host' => '127.0.0.1',
        'port' => 15672,
        'user' => 'guest',
        'password' => 'guest',
        'policyConfig' => [
            'pattern' => 'easy_queue_*',
            'definition' => [
                'ha-mode' => 'all',
                'ha-sync-mode' => 'manual',
            ],
            'priority' => 0,
            'apply-to' => 'queues',
            'name' => 'easy_queue',
        ]
    ],
    /** RPC队列 */
    'rpcQueue' => [
        'class' =>  \pzr\amqp\queue\RpcQueue::class,
        'host' => '127.0.0.1',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
        'queueName' => 'rpc',
        'exchangeName' => 'rpc',
        'routingKey' => 'rpc',
        'duplicate' => 2,
    ],
);
