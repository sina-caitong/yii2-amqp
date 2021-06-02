## 说明
看了AMQP也有很长时间了，一直停留在理论基础上。刚好又想学习Yii框架，那么就以实现AMQP练手吧！  
所以，这个类包的诞生纯属巧合，也许只是为了练手而已！  

## 基本用法

引入包：`composer require pzr/amqp` 

### 简介
队列类型：普通队列、延时队列、优先队列、RPC队列  
功能介绍：  
* 自动启用备份路由策略
* 支持客户端消息确认机制或者手动关闭
* 结合Yii的事件触发机制可以更好的处理事件
* 支持单条消息发送和批量发送
* 路由类型支持RabbitMQ的全部类型
* 启用了RPC队列
* 可选择队列副本，在启用队列副本后可以选择路由的方式。支持：随机、轮询、（更多待开发中）
* 支持多种序列化方式


### 队列的定义
首先在配置中创建 amqp.php 用来配置所有和amqp相关的配置。如果是本地测试则可以配置在 MY_amqp.php 中。然后在web.php或者console.php中引入配置文件。
```php
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
        // 'class' =>  \pzr\amqp\Amqp::class,
        'class' =>  \pzr\amqp\AmqpBase::class,
        'host' => '127.0.0.1',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
        // 'ackPolicy' => [
        //     'component' => 'PolicyAckRetryCount',  // 指定使用重试次数计次ACK策略, 如不指定, 会以AmqpBase中ackPolicy属性默认指定
        // ]
    ],
    /** 普通队列定义 */
    'easyQueue' => [
        'class' =>  \pzr\amqp\queue\EasyQueue::class,
        'host' => '127.0.0.1',
        'port' => 5672,
        'user' => 'guest',
        'password' => 'guest',
        'queueName' => 'easy_queue',
        'exchangeName' => 'easy_exchange',
        'routingKey' => 'easy',
        // 'serizer' => \pzr\amqp\serializers\PhpSerializer, //default value
        // 'dulicater' => \pzr\amqp\duplicate\DuplicateRandom, //default value
        // 'duplicate' => 0, //队列的副本数，不启用则设置为0
        // priority => 10, //定义优先级队列时配置
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
```
下文队列的定义用到的组件则是如上配置。

### 普通队列的定义
加载 amqp.php 中的 easyQueue 配置，然后在Controller中调用：  
```php
Yii::$app->easyQueue->on(AmqpBase::EVENT_BEFORE_PUSH, function ($event) {
    Yii::$app->easyQueue->bind(); //绑定队列，如果已经绑定可以注释此方法
    // $event->noWait = true; //关闭客户端的消息确认机制
});

Yii::$app->easyQueue->push(new CountJob([
    'count' => 1,
]);
```
这里的 CountJob 继承 \pzr\amqp\AmqpJob 接口，代码实现如：
```php
class CountJob extends \pzr\amqp\AmqpJob
{
    public $count;
    
    public function execute()
    {
        return $this->count;
    }
}
```

### 延时队列的定义
加载 amqp.php 中的 delayQueue 配置，然后在Controller中调用：  
```php
Yii::$app->delayQueue->on(AmqpBase::EVENT_BEFORE_PUSH, function ($event) {
    Yii::$app->delayQueue->bind();
});

Yii::$app->delayQueue->push(new CountJob([
    'count' => 1
]));
```

### 优先队列的定义
优先队列其实在普通队列或延时队列的基础上设置即可，有两种方式。  
（1）如普通队列在控制器中绑定的时候设置
```php
// 在绑定的时定义队列的最大优先级
Yii::$app->easyQueue->on(AmqpBase::EVENT_BEFORE_PUSH, function ($event) {
    Yii::$app->easyQueue->setPriority(10)->bind();
});

// 发送消息时定义每个消息的优先级
$job = new CountJob([
    'count' => 1,
    'priority' => 2,
]);
Yii::$app->easyQueue->push($job);
```
（2）在web.php中配置的时候定义队列的 priority 属性也可。

### RPC队列定义
加载 amqp.php 中的 rpcQueue 配置，然后在Controller中调用：  
```php
Yii::$app->rpcQueue->on(AmqpBase::EVENT_BEFORE_PUSH, function(PushEvent $event) {
    Yii::$app->rpcQueue->bind();
});

// 批量请求
for ($i=1; $i<=10; $i++) {
    $jobs[] = new RequestJob([
        'request' => 'request_' . $i,
    ]);
}
// qos：临时队列消费者预处理的数量；timeout：临时队列等待消费者处理的超时时间，单位s
$response = Yii::$app->rpcQueue->setQos(1)->setTimeout(3)->myPublishBatch($jobs);

return $response;
```
注意：RPC队列的消费者必须启用 amqp.php 中 的 rpcConsumer 配置才可使用。

### 消费者定义
加载 amqp.php 中的 consumer 配置，然后在Controller中调用：  
```php
class AmqpController extends Controller
{
    // 普通消费者定义
    public function actionConsumer($queueName, $qos)
    {
        Yii::$app->consumer->consume($queueName, $qos);
    }

    // RPC消费者定义
    public function actionRpcConsumer($queueName, $qos=1)
    {
        Yii::$app->rpcConsumer->consume($queueName, $qos);
    }
}
```
然后在shell中指定待消费的队列名称启动消费者，如下代码：
```shell
php yii amqp/consumer queueName qos #启动普通消费者
php yii amqp/rpc-consumer queueName qos #启动RPC消费者
```

### 消费者进程管理web界面
> 一般情况下推荐使用Supervisor管理消费者进程，但是为了更好的贴切本项目的消费者进程管理，我也尝试的做了一个管理。
> 支持日志跟踪、AMQP消费者的连接管理和登入功能，只看看首页吧！
![PHP](https://cdn.learnku.com/uploads/images/202008/26/68165/VbpoiRuNwX.png!large)

### 事件定义
| 事件 | 介绍 | 用法 |
| ------ | ------ | ------ |
| EVENT_BEFORE_PUSH | 推送消息前触发事件 | 取消消息确认机制、绑定队列、或对消息进行一些处理 |
| EVENT_AFTER_PUSH  | 推送消息后触发事件 | 记录日志或者其他 |
| EVENT_PUSH_NACK   | 启用消息确认机制，发送方收到服务端nack后触发 | 确保消息不会丢失处理 |
| EVENT_PUSH_ACK    | 启用消息确认机制，发送方收到服务端ack后触发  | 日志记录或者其他 |
| EVENT_BEFORE_EXEC | 消费者消费前  | 日志记录或者其他 |
| EVENT_AFTER_EXEC  | 启用消息确认机制，发送方收到服务端ack后触发  | 日志记录或者其他 |
<hr>

### 配置镜像策略
首先在web.php中配置：
```php
'policy' => [
    'class' =>  \pzr\amqp\api\Policy::class,
    'host' => '127.0.0.1', //your hostname
    'port' => 15672,    //default port
    'user' => 'guest', //default user name
    'password' => 'guest', //default password
    'policyConfig' => [ //policy config
        'pattern' => 'easyqueue*', //匹配所有符合该正则的队列
        'definition' => [
            'ha-mode' => 'all', //default all, choose one of [all, exactly, nodes]
            'ha-sync-mode' => 'manual', // default manual, choose one of [manual, automatic]
            // 'ha-params' => [], //depend on ha-mode
        ],
        'priority' => 0, //default 0
        'apply-to' => 'queues', //choose one of [all,queues,exchanges]
        'name' => 'easyQueue', //free name
    ]
]
```
在Controller中调用
```php
Yii::$app->policy->setPolicy();
```

<hr>

### 功能扩展
* 增加队列绑定的严谨模式，防止已创建的队列被无意篡改。  
* 集成AMQPAPI功能
* 增加消息分发的自动路由策略  

#### 开启严谨模式
在 amqp.php 配置中配置 "strict" 为true 即可。如：
```php
'<Queue>' => [
    'class' =>  \pzr\amqp\queue\<QueueName>::class,
    // ...省略
    'strict' => true,
```
开启队列创建的严谨模式之后，在创建队列之前会先查看是否已经有队列创建，如果有则不再创建。  
需要注意的是：如果开启了队列副本且已创建个别队列的情况下，增加副本数量也不会新增队列。  

这种模式只是简单的做了一个队列防止再被创建的保护，但是如果需要对AMQP的元数据（队列、交换器、绑定关系）进行更多的保护，可以通过调用 http://127.0.0.1:15672/api/definitions/ 查看队列的元数据并且做相应的保护。

#### 集成AMQPAPI
默认情况下会复用部分当前队列的配置属性创建API对象，可以通过 `Yii::$app->easyQueue->getApi()` 获取AmqpApi 对象。

但是如果想要申明自己的API对象，可以有两种方式：  

1、复用Yii已定义的组件  
```php
'easyQueue' => [
        'class' =>  \pzr\amqp\queue\EasyQueue::class,
        // ...省略
        'api' => [
            'component' => 'amqpApi',
        ]
    ],
```
2、配置API属性，实例化AmqpApi对象  
```php
'api' => [
    'class' => \pzr\amqp\api\AmqpApi::class,
]
```
3、配置API属性，实例化Policy对象
```php
'api' => [
    'class' => \pzr\amqp\api\Policy::class,
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
]
```

实例化Policy对象之后，就可以在事件 EVENT_BEFORE_PUSH 之前设置策略，如
```php
Yii::$app->easyQueue->on(AmqpBase::EVENT_BEFORE_PUSH, function(PushEvent $event) {
    Yii::$app->easyQueue->bind();
    // 设定策略
    Yii::$app->easyQueue->getApi()->setPolicy();
});
```



<hr>

### AMQP ACK Policy
目前实现了3种ACK策略，它们都继承于AckPolicyInterface：

| 策略 | 名称 | 说明 |
| ------ | ------ | ------ |
| PolicyAckRetryCount | 重试次数计数策略 | 会对当前任务的失败次数作计数统计，当失败次数超过一定数值时，nack放弃处理 |
| PolicyAckNormal  | 普通ACK策略 | 当前任务处理失败时，默认requeue |
| PolicyNoAck   | 无需ACK策略 | 无需ACK |


3种策略均以组件的形式配置于yii/config/amqp.php，并作为组件注册到console.php中
```php
// ACK Policy
'PolicyAckRetryCount' => [
    'class' => \pzr\amqp\ack\PolicyAckRetryCount::class,
    'retryLimit' => 5       // 可指定属性覆盖
],
'PolicyAckNormal' => [
    'class' => \pzr\amqp\ack\PolicyAckNormal::class,
],
'PolicyNoAck' => [
    'class' => \pzr\amqp\ack\PolicyNoAck::class,
],
```

ACK策略一般配置在消费者中，如不指定，以AmqpBase中ackPolicy属性的默认指定为准。
```php
/** 普通消费者 */
'consumer' => [
    // 'class' =>  \pzr\amqp\Amqp::class,
    'class' =>  \pzr\amqp\AmqpBase::class,
    'host' => '127.0.0.1',
    'port' => 5672,
    'user' => 'guest',
    'password' => 'guest',
    // 'ackPolicy' => [
    //     'component' => 'PolicyAckRetryCount',  // 指定使用重试次数计次ACK策略, 如不指定, 会以AmqpBase中ackPolicy属性默认指定
    // ]
],
```
<hr>