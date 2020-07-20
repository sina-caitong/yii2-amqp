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
        
        // 不想每次都传config可以试试define('YII_CONSOLE_PATH', '\path')
        $config = require 'console.php';
        $yii = new MyYii($config);
        // jobs的申明必须在MyYii之后，因为MyYii引入了一些必要文件。
        $jobs[] = new ServeJob([
            'action' => 'sayHello',
            'params' => ['word'=>'hello world!'],
        ]);
        // 返回的是\pzr\amqp\Response
        $response = $yii->request([
            'rpc/publish',
            $jobs,
            1,  //qos
            3   //timeout
        ]);
        $response = $response->getResponse();
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
（因为serializer序列化对象只能序列化对象的非静态变量，所以无法直接返回对象）  
解决方案：在服务端封装好被调用的逻辑，客户端请求的时候告诉服务端想调用的函数也可以达到效果！


### 命名空间的问题
如果是Composer项目，并且类都是引入的Composer的自动加载函数，那么契合度最高。  

可是在RPC消费者执行时，因为自身项目引入了自己的自动加载函数，而非Composer的autoload。从而导致加载类异常。  
1、自身项目是有命名空间的，这种情况可以在调用MyYii之前引入自己的autoload.php，也可以修改MyYii引入。  
2、自身项目没有用命名空间，导致在自动加载类时异常。需要自己处理如何自动加载该类。  
3、在第2种的情况下，将要实例化的类由于在构造方法中存在其他没有命名空间的对象需要实例化，那么可能再将报错。  

如果是第一种情况，在请求方指定object='\namespace\objectNmae'  
如果是第二种情况，因为没命名空间，因此需要找到该类的完整路径并且引入才行。  

### RPC消费者因异常而退出的可能
1、RPC控制器中的异常，因为没有捕获处理导致异常

### A项目对B的RPC调用压测

### Dagger框架 RPC调用
Dagger框架比较早，自身也没有引入命名空间，而且在项目中也存在个人写法上引入命名空间。因此需要解决命名空间的问题。  
场景描述：cms_caitong 调用 match_lottery 类下的方法。
解决问题：在match_lottery下的一些配置可以在cms_caitong下无需重复配置，直接读取即可。因此不用每次在修改配置的时候需要修改双份。  
具体流程： 

#### 流程
* 复制 pzr\amqp 的 demo 目录到 match_lottery 根目录下  
* 修改 demo 为 yii
* 修改 amqp.php 的配置
* 修改 yii/yii 脚本，引入 Dagger 的自动引入规则和自己的命名空间引入规则
* 增加自己的RPC调用业务逻辑代码
* 用supervisor管理消费者进程

其中在修改 yii/yii 脚本是根据自身项目情况而定，因为在启动消费者的时候会通过这个yii启动。所以在启动消费者之前先引入自动加载函数的文件相关  
```php
// 自动加载Dagger底层的类和项目中没有启用命名空间的类。
// 注：这里仅仅是自身测试如此引入
require $baseDir . '/xxxxDagger/x.0/init.extern.php';
// 自动加载个人使用命名空间的类，符合PSR-0规范
require $baseDir . '/myAutoload.php';
```
在Dagger的入口文件index.php中配置YII_CONSOLE_PATH
```php
define('YII_CONSOLE_PATH', __DIR__ . '/yii/config/console.php');
```
准备工作好了，接下来看如何工作了。

#### cms_caitong 在某个控制器下配置RPC调用
```php
class xxx
{
    protected $yii;
    public function __construct() {
        // ...
        $yii = new MyYii();
        // RPC调用
        $jobs[] = new MatchLotteryJob([
            'object' => '\model\sellFree\Config',
            'action' => 'getMsgCode',
            'params' => [],
            'uuid' => uniqid(true), //15f155d7a58629
        ]);
        $jobs[] = new MatchLotteryJob([
            'object' => 'PayConfig',
            'action' => 'getChargeWay',
            'params' => [],
            'uuid' => uniqid(true), //15f155d7a5864d
        ]);
        $response = $yii->request([
            'rpc/match-lottery',
            $jobs
        ]);

        // ...
    }
}
```
结果是：
```php
array (size=2)
  '15f155d7a58629' => array(...)
  '15f155d7a5864d' =>  array(...)
```
通过消息的唯一性UUID获取对应的结果。

#### RPC调用日志如何跟踪
问题一：客户端发送的消息成功了吗？
解决：使用Yii的事件绑定可以跟踪发送是否成功。如在发送方增加事件  
```php
// 消息发送成功会触发此方法
Yii::$app->matchLottery->on(AmqpBase::EVENT_PUSH_ACK, function(PushEvent $event) {
    //此处则可以记录日志分析
});
```

问题二：RPC队列是否处理了客户端发送的消息呢？  
解决：RPC队列的消费发生在被调用方，因此需要在被调用方启动RPC队列处增加相应事件处理，如：
```php
public function actionRpcConsumer($queueName, $qos=1)
{
    Yii::$app->rpcConsumer->on(AmqpBase::EVENT_AFTER_EXEC, function(ExecEvent $event) {
        // RPC消费者处理完成后的事件处理
    });
    Yii::$app->rpcConsumer->consume($queueName, $qos);
}
```

问题三：我可以监控RPC临时队列是否消费吗？  
回答：不可以  

