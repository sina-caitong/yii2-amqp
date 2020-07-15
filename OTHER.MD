## 其他

### 使用路由类型 Topic
* "*" 匹配的是一个词
* "#" 匹配的是多个词

举例：  
*.log.* 可以匹配：warning.log.test、 debug.log.amqp  
*.log.# 可以匹配：warning.log.amqp.test、 debug.log.queue.amqp  

<pre>
 可以看到都用了 "*."、".*"、".#" 这种写法，倘若我不用符号 "." 而用 "_" 会如何？（没错，在底层队列副本的默认拼接符号就是 "_" ）
</pre>

测试： 普通队列
| 队列副本 | exchangeType | routingKey | exchangeName | queueName | 消息的routingKey | 消息路由 |
| ------ | ------ | ------ | ------ | ------ | ------ | ------ |
| 不启用 | topic | \*\_log\_\* | easy_exchange | easy_queue | warning_log_test | 备份队列 |
| 不启用 | topic | \*\.log\.\* | easy_exchange | easy_queue | warning.log.test | easy_queue |
| 启用 | topic | \*\.log\.\* | easy_exchange | easy_queue | warning.log.test | easy_queue |
| 启用 | topic | \*\.log\.\*\. | easy_exchange | easy_queue | warning.log.test. | easy_queue_1 |

<pre>
启用队列副本 duplicate=2 后，
        *.log.*_0 => queue_0
        *.log.*_1 => queue_1
指定消息的路由键为 warning.log.test ，在路由的随机分配下 routingKey=warning.log.test_0 。因为 test_0 被看做是一个单词，因此最终匹配到了easyQueue。这肯定不是希望看到的。

于是将路由键 "routingKey=*.log.*." 注意到最后增加了"."符号。然后指定消息路由键为 "warning.log.test." ，也增加了一个符号"."。最终随机策略下的"warning.log.test._1" 路由到了正确的队列easy_queue_1 。
</pre>
<b>所以最终得到的结论就是：路由类型为 topic ，一定记得用符号"."和"\*"号连接。如果是开头位置就".\*"，如果是结尾位置最好用".\*." </b>

当然，这里的前提是自己指定消息路由键去发送消息，如：
```php
Yii::$app->easyQueueTopic->on(AmqpBase::EVENT_BEFORE_PUSH, function(PushEvent $event) {
    Yii::$app->easyQueueTopic->bind();   //绑定队列，如果已经绑定可以注释此方法
    // $event->noWait = true;          //默认false开启客户端消息确认机制，true则关闭
});

// 发送单条消息
Yii::$app->easyQueueTopic->push(new CountJob([
    'count' => 1,
]), 'warning.log.test.');
```
如果不指定消息路由键那么就会自动以配置中的路由键配合随机策略分发模式发送消息，也不会有问题。

### 为什么配置镜像策略？
原因：为了防止网络原因而产生的网络分区带来的一些问题。 

网络分区是什么？  
> 假设我在两台服务器上配置了RabbitMQ的主从node1和node2，正常情况下这两台服务器之间是通信的。倘若因为搬机房或者其他的> 原因导致node1和node2之间无法通信，并且处于两个网络环境，那么这就产生了网络分区。  

带来的危害  
> 假设在网络分区之前消息的路由都在各自的节点，即使发生网络分区也不会有任何问题：  
> node1: message -> exchange1 -> queue1  
> node2: message -> exchange2 -> queue2  
>
> 倘若是如下这种，那么就会导致在node1、node2节点上的消息无法正确路由，在node1上消费queue2也出现问题。  
> node1: message -> exchange1 -> queue2  
> node2: message -> exchange2 -> queue1  

配置镜像后，则在node1和node2都各自有queue1和queue2的镜像，因此网络分区后不再受影响。但是需要考虑如何恢复在一个分区。  

### 网络分区后如何恢复


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

### RPC队列如何使用

### 测试报告
https://www.yuque.com/docs/share/8b4eb7b8-105a-42d3-85ee-17f9a65a0a33?#