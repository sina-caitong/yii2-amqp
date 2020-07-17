## RPC使用

为了能够使用RPC功能，做了如下处理：  
* 参考Yii Console 的调用方式，实现了 MyYii 对象。
* 客户端发送的请求必须继承 AmqpJob

假设有项目A和项目B，具体使用RPC功能过程：  
* 在A|B项目根目录下，执行 composer require pzr/amqp:dev-master , 引入项目
* 复制 demo 的代码到根目录下，将 demo 重命名为 yii (或其他)
* 配置 config/amqp.php，并且在 console.php 中引入组件配置
* 可能需要修改 yii/yii 文件 从而正确引入 vendor/autoload.php
* 在models目录下编写自己的Job类
* 在commands下编写自己的Controller类
* 绑定rpc队列，并且在被调用的项目下启动rpc队列的消费者进程
* 最后即可在任意处通过 MyYii 调用Controller下的实现方法

或者如果直接在composer下的pzr/amqp/src/demo修改也是可以的。如此更是简单。  

情景1：A在 Client 类下希望调用 B 的 Serve 类的方法
1、在amqp.php 中 配置RPC队列，并且在console.php中引用配置：
```php
'b_rpcQueue' => [
    'class' =>  \pzr\amqp\queue\RpcQueue::class,
    'host' => '127.0.0.1',
    'port' => 5672,
    'user' => 'guest',
    'password' => 'guest',
    'queueName' => 'b_rpc_queue',
    'exchangeName' => 'b_rpc_exchange',
    'routingKey' => 'b_rpc_routing',
    // 'duplicate' => 2, //此处不启用队列副本
    ],
```

2、在models下编写Job类
```php
class ServeJob extends AmqpJob
{
    public $action;
    public $params;
    public function execute() {
        $serve = new Serve();
        // 省去各种校验
        return call_user_func_array([$serve, $this->action], $this->params);
    }
}
```

3、在commands下编写RpcController类，示例代码如：
```php
class RpcController extends Controller
{
    // RPC通用方法
    public function actionPublish($jobs, $qos=1, $timeout=3) {
        if (empty($jobs)) {
            return null;
        }
        ii::$app->b_rpcQueue->on(AmqpBase::EVENT_BEFORE_PUSH, function(PushEvent $event) {
            Yii::$app->b_rpcQueue->bind();
        });
        $response = Yii::$app->b_rpcQueue->setQos($qos)
            ->setTimeout($timeout)
            ->myPublishBatch($jobs);
        return $response;
    }
}
```
3、在 Client.php 中通过 MyYii 调用rpc/publish。
```php
class Client
{
    public function callServer() {
        $jobs[] = new ServeJob([
            'action' => 'sayHello',
            'params' => ['word'=>'hello world!'],
        ]);
        // 不想每次都传config可以试试define('YII_CONSOLE_PATH', '\path')
        $config = require 'console.php';
        $yii = new MyYii($config);
        // 返回的是\pzr\amqp\Response
        $response = $yii->request([
            'rpc/publish',
            $jobs,
            1,  //qos
            3   //timeout
        ]);
        $response = $response->getResponse();
        // 临时队列的消息是经过序列化处理的。但是客户端和服务端的序列化方式必须一致
        $response = unserialize($response);
        var_dump($response);
    }
}
```

4、在B项目中的Serve类代码如：
```php
class Serve
{
    public function sayHello($word) {
        return $word;
    }
}
```

5、在B项目底下启动消费者进程测试，如果是正式情况请用supervisor管理消费者进程
```shell
php yii amqp/rpc-consumer b_rpc_queue 10
```

那么在正常情况下会得到：hello world！一切似乎都值得庆祝，但是花这么大功夫不是为了调用这么简单的方法，能不能调用更复杂的，如对象！  
答案是：不可以！
（实践之后因为serializer序列化对象只能序列化对象的非静态变量，所以无法直接返回对象）  
解决方案：在服务端封装好被调用的逻辑，客户端请求的时候告诉服务端想调用的函数也可以达到效果！
