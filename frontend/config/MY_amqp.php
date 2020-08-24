<?php

use pzr\amqp\cli\handler\AmqpHandler;

return array(
    'rpcConsumer' => [
        'class' =>  \pzr\amqp\RpcAmqp::class,
        'host' => '10.71.13.24',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
    ],
    'consumer' => [
        'class' =>  \pzr\amqp\AmqpBase::class,
        'host' => '10.71.13.24',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
    ],
    'easyQueue' => [
        'class' =>  \pzr\amqp\queue\EasyQueue::class,
        // 'as log' => \pzr\amqp\LogBehavior::class,
        'host' => '10.71.13.24',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
        'queueName' => '07_31_easy_queue',
        'exchangeName' => '07_31_easy_exchange',
        'duplicate' => 2,
        'routingKey' => '07_31_easy',
        'duplicater' => \pzr\amqp\duplicate\DuplicatePoll::class,
        'strict' => true,
        // 'api' => [
        //     // 'component' => 'amqpApi',
        //     // 'class' => \pzr\amqp\api\AmqpApi::class,
        //     'class' => \pzr\amqp\api\Policy::class,
        //     'policyConfig' => [
        //         'pattern' => '07_14_easy_queue_*',
        //         'definition' => [
        //             'ha-mode' => 'all',
        //             'ha-sync-mode' => 'manual',
        //         ],
        //         'priority' => 0,
        //         'apply-to' => 'queues',
        //         'name' => '07_14_easy_queue',
        //     ]
        // ]
        // Other driver options
    ],
    'delayQueue' => [
        'class' =>  \pzr\amqp\queue\DelayQueue::class,
        // 'as log' => \pzr\amqp\LogBehavior::class,
        'host' => '10.71.13.24',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
        'queueName' => '07_14_queue',
        'exchangeName' => '07_14_exchange',
        'exchangeType' => 'topic',
        'routingKey' => '07_14_delay.*.',
        'delayQueueName' => '07_14_delay_queue',
        'delayExchangeName' => '07_14_delay_exchange',
        'delayExchangeType' => 'topic',
        'ttl' => 5000, //ms
        'duplicate' => 2,
        // Other driver options
    ],
    'amqpApi' => [
        'class' =>  \pzr\amqp\api\Policy::class,
        'host' => '10.71.13.24',
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
            'name' => '07_08easy_queue',
        ]
    ],
    'rpcQueue' => [
        'class' =>  \pzr\amqp\queue\RpcQueue::class,
        'host' => '10.71.13.24',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
        'queueName' => 'rpc',
        'exchangeName' => 'rpc',
        'routingKey' => 'rpc',
        'duplicate' => 2,
    ],

    'easyQueueTopic' => [
        'class' =>  \pzr\amqp\queue\EasyQueue::class,
        'as log' => \pzr\amqp\LogBehavior::class,
        'host' => '10.71.13.24',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
        'queueName' => '07_15_easy_queue',
        'exchangeName' => '07_15_easy_exchange',
        'exchangeType' => 'topic',
        'routingKey' => '*.log.*.',
        'duplicate' => 2,
        // Other driver options
    ],
    'processQeueu' => [
        'class' =>  \pzr\amqp\queue\EasyQueue::class,
        // 'as log' => \pzr\amqp\LogBehavior::class,
        'host' => '10.71.13.24',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
        'queueName' => AmqpHandler::QUEUE,
        'exchangeName' => AmqpHandler::QUEUE,
        'exchangeType' => 'direct',
        'routingKey' => '',
        'duplicate' => 1,
        'strict' => true,
    ],
);
