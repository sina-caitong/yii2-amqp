## 基本用法
### 队列的定义
队列类型：普通队列、延时队列、优先队列

#### 普通队列的定义
在web.php中配置如下：
```php
'easyQueue' => [ //your defined component name
    'class' =>  \pzr\amqp\queue\EasyQueue::class,
    'host' => '127.0.0.1', //your hostname or ip
    'port' => 5672, //default port
    'user' => 'guest', //default username
    'password' => 'guest', //default password
    // setting easyQueue params
    'queueName' => 'easyQueue', //your queueName
    'exchangeName' => 'easyExchange', //your exchangeName
    // 'exchangeType' => 'direct', //default value
    'routingKey' => 'easy', //default empty string
    // 'serizer' => \pzr\amqp\serializers\PhpSerializer, //default value
    // 'dulicater' => \pzr\amqp\duplicate\DuplicateRandom, //default value
    // 'duplicate' => 0, //队列的副本数，不启用则设置为0
    // priority => 10, //定义优先级队列时配置
]
```
然后在Controller中调用：
```php
// 这里第一次调用必须申明和绑定队列和路由器，如果已经绑定则这个方法可以省略
Yii::$app->easyQueue->on(AmqpBase::EVENT_BEFORE_PUSH, function ($event) {
    Yii::$app->easyQueue->bind();
});

Yii::$app->easyQueue->push(new DownloadJob([
    'url' => "http://example.com/image.jpg",
    'file' => '/tmp/image.jpg',
]);
```
这里的DownloadJob 实现 \pzr\amqp\JobInterface 接口，代码实现如：
```php
class DownloadJob extends \yii\base\BaseObject implements \pzr\amqp\JobInterface
{
    public $url;
    public $file;
    public $priority; //设置优先级队列中消息优先级的属性
    
    public function execute()
    {
        file_put_contents($this->file, file_get_contents($this->url));
    }

    public function setPriority($priority)
    {
        $this->priority = $priority;
        return $this;
    }

    public function getPriority()
    {
        return $this->priority?:0;
    }
}
```

#### 延时队列的定义
在web.php中的配置如下：
```php
'delayQueue' => [
    'class' =>  \pzr\amqp\queue\DelayQueue::class,
    'host' => '127.0.0.1', //your hostname or ip
    'port' => 5672, //default port
    'user' => 'guest', //default username
    'password' => 'guest', //default password
    // setting normalQueue params
    'queueName' => 'normalQueue', //your queueName
    'exchangeName' => 'normalExchange', //your exchangeName
    // 'exchangeType' => 'direct', //default value
    'routingKey' => 'normal', //default empty string
    // setting delayQueue parmas
    'delayQueueName' => 'delayQueue',
    'delayExchangeName' => 'delayExchange',
    'delayExchangeType' => 'direct',
    'delayRoutingKey' => 'delay',
    'ttl' => 5000, //5000ms
    // 'serizer' => \pzr\amqp\serializers\PhpSerializer, //default value
    // 'dulicater' => \pzr\amqp\duplicate\DuplicateRandom, //default value
    // 'duplicate' => 0, //队列的副本数，不启用则设置为0
    // priority => 10, //定义优先级队列时配置
]
```
然后在Controller中调用：
```php
// 这里第一次调用必须申明和绑定队列和路由器，如果已经绑定则这个方法可以省略
Yii::$app->delayQueue->on(AmqpBase::EVENT_BEFORE_PUSH, function ($event) {
    Yii::$app->delayQueue->bind();
});

Yii::$app->delayQueue->push(new DownloadJob([
    'url' => "http://example.com/image.jpg",
    'file' => '/tmp/image.jpg',
]));
```

#### 优先队列的定义
优先队列其实在普通队列或延时队列的基础上设置即可，有两种方式。
（1）如普通队列在控制器中绑定的时候设置
```php
// 在绑定的时定义队列的最大优先级
Yii::$app->easyQueue->on(AmqpBase::EVENT_BEFORE_PUSH, function ($event) {
    Yii::$app->easyQueue->setPriority(10)->bind();
});

// 发送消息时定义每个消息的优先级
$job = (new DownloadJob([
    'url' => "http://example.com/image.jpg",
    'file' => '/tmp/image.jpg',
]))->setPriority(2); // 当然也可以放在job的构造函数中设置priority属性
Yii::$app->easyQueue->push($job);
```
（2）在web.php中配置的时候定义 priority 属性即可。


### 消费者定义
在console.php中配置
```php
'consumer' => [
    'class' =>  \pzr\amqp\MyAmqp::class,
    'host' => '127.0.0.1', //your hostname
    'port' => 5672, //default port
    'user' => 'guest', //default user
    'password' => 'guest',//default password
]
```
然后在Commonds下创建控制器，如示例：
```php
class AmqpController extends Controller
{
    public function actionIndex($queueName, $qos, $consumerTag)
    {
        Yii::$app->consumer->consume($queueName, $qos, $consumerTag);
    }
}
```
然后指定待消费的队列名称启动消费者，如下代码：
```php
php yii amqp/index queueName qos
```

虽然我想对消费者进行更多的封装，比如结合队列副本和消费者的数量等。但是这些都不如利用supervisor管理消费者进程更加有效。在supervisor中配置消费者进程：
```shell
; 设置进程的名称，使用 supervisorctl 来管理进程时需要使用该进程名
[program:queueName]
; 执行命令的目录
directory=/Users/pzr/project/;
; 执行的命令
command=/usr/bin/php yii amqp/index queueName
; 进程的数量，即消费者的数量。默认是1
numprocs=3; 
; 启动进程的用户
user=root;
; 程序崩溃时自启动，默认启动的次数为3次
autorestart=true;
; 重定向输出日志
stdout_logfile=/var/log/supervisord/queueName.log
loglevel=info;
```

### 事件定义

<hr>

## 更多细节

### 配置镜像策略
首先在web.php中配置：
```php
'amqpApi' => [
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
Yii::$app->amqpApi->setPolicy();
```

#### 为什么配置镜像策略？
原因：为了防止网络原因而产生的网络分区带来的一些问题。 

网络分区是什么？  
假设我在两台服务器上配置了RabbitMQ的主从node1和node2，正常情况下这两台服务器之间是通信的。倘若因为搬机房或者其他的原因导致node1和node2之间无法通信，并且处于两个网络环境，那么这就产生了网络分区。  

带来的危害  
假设在网络分区之前消息的路由都在各自的节点，即使发生网络分区也不会有任何问题：  
node1: message -> exchange1 -> queue1  
node2: message -> exchange2 -> queue2  

倘若是如下这种，那么就会导致在node1、node2节点上的消息无法正确路由，在node1上消费queue2也出现问题。  
node1: message -> exchange1 -> queue2  
node2: message -> exchange2 -> queue1  

配置镜像后，则在node1和node2都各自有queue1和queue2的镜像，因此网络分区后不再受影响。但是需要考虑如何恢复在一个分区。  


### 备份路由器
为了防止消息无法正确路由到指定的队列而丢失，因此在代码的底层默认配置了备份路由器，队列的名称以“_AE”结尾。

### 队列副本是什么？
<pre>
队列副本示意图如下：
                         |-> routingKey_0 -> queueName_0
message -> exchangeName -|-> routingKey_1 -> queueName_1
                         |-> routingKey_2 -> queueName_2
无队列副本示意图如下：
message -> exchangeName -> routingKey -> queueName
</pre>

### 为什么要设定队列副本
原因：减少流控制的影响
<pre>
流控制是什么？
Erlang进程之间不共享内存（binary类型除外），因此进程的通信是通过“进程邮箱”交换信息，Erlang对进程邮箱的大小没有限制。如果持续高速的往进程邮箱中投递消息，那么就会导致进程邮箱的内存溢出从而致使进程崩溃。为了防止这种情况发生，因此从2.8.0之后增加了流控制并且采用了信用证算法。如下三个进程：

rate=3      rate=2      rate=1
B.value=12  C.value=12  y.value
x.value     A.value=?   B.value
processA -> processB -> processC

（1）processB 每次向 processC 投递一次消息，C.value-- 
（2）当 C.value==0 时 processB 不再向 processC 发送消息，也不再接收 processA 的消息。
（3）processB 的 A.value 表示接受多少条消息后，就通知 processA 增加 B.value。如此就能持续发送消息。
（4）当上游发送速率大于下游接收的速率，那么上游的value就会被耗光，导致进程阻塞一直延续到最上游。
（5）当上游进程接收到下游进程增加value的通知后，会一直通知上游解除阻塞状态。

由此可见，流控机制将消息发送进程的发送速率限制在消息处理进程的处理能力范围之内。

举个例子：
A.value=12 B.value=12 C.value=12
processA 的发送速度是 3，processB 的发送速度是 2
processA 的 B.value=0 时，processB 的 C.value=4,A.value=0，此时 processA 不在接收和发送消息并且堵塞状态一直延续到它的最上游。而 processB 则继续向 processC 发送消息。

在AMQP中，流控制是如下示意图：
rabbit_reader(Connection) -> rabbit_channel -> rabbit_amqqueue_process -> rabbit_msg_store

推送消息时，流控制的瓶颈一般出现在队列进程，因此针对这种情况增加了副本概念。参考队列副本示意图可知，没有副本的情况下消息都是路由到一个队列，但是增加队列副本之后则路由到多个队列，因此多个队列进程在同时工作。
</pre>

### 客户端消息确认机制
客户端消息确认机制确实会降低消息发送的吞吐量，但是却可以保证消息的可靠性。如果发送的消息在没有被AMQP正确接收的情况下，可以通过消息确认机制监听到之后进行操作。  
AMQP对客户端消息确认机制有两种操作，一种是同步的，一种是异步的。  
同步消息确认机制  
```php
try{
    $channel->tx_select();
    $channel->basic_publish(...);
    $channel->tx_commit();
} catch (Exception $e) {
    $channel->tx_rollback();
}
```
异步消息确认机制  
```php
$channel->set_nack_handler(function (AMQPMessage $payload) {
    // 增加 EVENT_PUSH_NACK 事件
});

$channel->set_ack_handler(function (AMQPMessage $payload) {
    // 增加 EVENT_PUSH_ACK 事件
});

$channel->confirm_select();

$channel->basic_publish(...);

$channel->wait_for_pending_acks();
```

#### 如何取消客户端消息确认机制？
在 EVENT_BEFORE_PUSH 事件之前增加 $event->noWait = true 即可。如：
```php
Yii::$app->delayQueue->on(AmqpBase::EVENT_BEFORE_PUSH, function ($event) {
    $event->noWait = true;
});
// 发送消息
```


### 消费者消息确认机制
消费者消费的对象都是实现的JobInterface接口，所以调用Job实例的execute方法实现消费的逻辑。如果execute返回的是true，则表示消费成功并且队列的数量减一；如果返回的是false则表示消费失败，消息会回到消息队列等待再次消费。为了避免重复消费的情况，所以在消费的逻辑里面最好加上幂等性（数据一致性）判断。

消息被消费之前会触发 EVENT_BEFORE_EXEC 事件  
消息被消费之后会触发 EVENT_AFTER_EXEC 事件  

### QOS如何设置？ 
QOS是什么意思？  
消费者预处理的消息数量  

影响QOS设定的因素  
1、消息投递的时间: deliver  
2、消息处理的时间: handle  
3、消息ack的时间: ack  
4、网络因素: other  

QOS = (deliver + handle + ack) / handle  

假设有两个消费者，设定QOS=10。那么在启动消费者后consumer1从队列中预取10个消息，consumer2再从队列中预取10个消息。此时的nack=20，ack=0。如果有消息被确认，那么ack++，消费者再从队列中预取待消费的消息。

https://www.yuque.com/zhangyu-tryjv/ebrhh2/goq33o 


### 如何结合Yar工作？
之前结合Yar确实可以达到跨项目远程调用，但是因为Yar底层也是实现的TCP，因此无论如何TCP的时耗是不可忽略的。为了保证消费者尽可能的快，所以还是舍去Yar。因为在测试报告的时候发现消费者时间设定为1s的时候AMQP消费的速度会特别慢，无论怎么增加消费者也无济于事。只有保证消费者消费的速度越快越好！
### 测试报告
https://www.yuque.com/docs/share/8b4eb7b8-105a-42d3-85ee-17f9a65a0a33?#