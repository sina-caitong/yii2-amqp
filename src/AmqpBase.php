<?php

namespace pzr\amqp;

use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use pzr\amqp\api\AmqpApi;
use pzr\amqp\api\ApiInterface;
use pzr\amqp\api\Policy;
use pzr\amqp\event\ExecEvent;
use pzr\amqp\event\PushEvent;
use pzr\amqp\exception\InvalidArgumentException;
use pzr\amqp\exception\MissPropertyException;
use pzr\amqp\exception\UnknowException;
use pzr\amqp\QueueInterface;
use pzr\amqp\serializers\SerializerInterface;
use pzr\amqp\serializers\PhpSerializer;
use yii\base\Component;
use yii\base\Event;
use yii\base\Application as BaseApp;
use yii\di\Instance;

/**
 * 对AMQP的基本方法的封装
 */
class AmqpBase extends Component implements QueueInterface
{
    public $host = 'localhost';
    public $port = 5672;
    public $user = 'guest';
    public $password = 'guest';
    public $queueName = 'queue';
    public $exchangeName = 'exchange';
    public $vhost = '/';
    public $routingKey = '';
    public $exchangeType = ExchangeType::DIRECT;
    public $arguments;

    /** @var bool true则开启严谨模式，校验队列是否已绑定 */
    public $strict = false;

    /** @var SerializerInterface|array */
    public $serializer = PhpSerializer::class;

    /** @var int 定义队列的优先级 */
    public $priority;

    /** @var AmqpApi */
    protected $api;

    protected $channel;
    protected $connection;



    /**
     * @event PushEvent
     */
    const EVENT_BEFORE_PUSH = '_beforePush';
    /**
     * @event PushEvent
     */
    const EVENT_AFTER_PUSH = '_afterPush';
    /**
     * @event ExecEvent
     */
    const EVENT_BEFORE_EXEC = '_beforeExec';
    /**
     * @event ExecEvent
     */
    const EVENT_AFTER_EXEC = '_afterExec';
    /**
     * @event ExecEvent
     */
    const EVENT_AFTER_ERROR = '_afterError';
    /**
     * @event PushEvent
     */
    const EVENT_PUSH_NACK = '_pushNack';
    /**
     * @event PushEvent
     */
    const EVENT_PUSH_ACK  = '_pushAck';


    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->serializer = Instance::ensure($this->serializer, SerializerInterface::class);
        Event::on(BaseApp::class, BaseApp::EVENT_AFTER_REQUEST, function () {
            $this->close();
        });
    }

    /**
     * 绑定队列和路由的方法实现
     */
    public function bind()
    {
    }

    /**
     * 队列申明
     *
     * @param string $queueName
     * @param array $arguments
     * @return void
     */
    final public function queueDeclare($queueName, $arguments = [])
    {
        if (empty($queueName)) {
            throw new MissPropertyException('Missing necessary parameters of queueName');
        }
        if (!empty($arguments)) {
            // 设置优先级队列
            if ($this->priority && is_int($this->priority)) {
                $arguments['x-max-priority'] = $this->priority;
            }
            $arguments = new AMQPTable($arguments);
        }

        /*
            $queue = '',            //队列名称
            $passive = false,       //被动的，消极的. true如果queue存在则正常返回，不存在则关闭当前channel。
            $durable = false,       //持久化，写入的消息会被存入磁盘而持久化
            $exclusive = false,     //独有的，排外的。只对首次申明他的连接可见，一旦连接断开，则该queue也会自动删除。
            $auto_delete = true,    //前提必须是至少有一个与之绑定，之后所有与之绑定的都自动解绑。 不能错误的理解为：自动删除
            $nowait = false,        //是否返回服务消息，如果true则不返回，false返回.返回的是queue.declare_ok字符串
            $arguments = null,      //这个里面可以选填很多的规定的参数
            $ticket = null
         */
        try {
            $this->channel->queue_declare($queueName, false, true, false, false, true, $arguments);
        } catch (Exception $e) {
            throw new UnknowException($e->getMessage());
        }
    }

    /**
     * @param $exchangeName
     * @param $exchangeType
     * @param array $arguments
     * @return mixed
     */
    final public function exchangeDeclare($exchangeName, $exchangeType, $arguments = [])
    {
        if (empty($exchangeName) || empty($exchangeType)) {
            throw new MissPropertyException('Missing necessary parameters of exchangeName or exchangeType');
        }
        if (!ExchangeType::isDefined($exchangeType)) {
            throw new MissPropertyException('exchangeType\'s value is not defined');
        }
        if (!empty($arguments)) {
            $arguments = new AMQPTable($arguments);
        }

        /*
            $exchangeName, 路由名称
            $type, 路由的方式，分为四种。direct,topic,fanout, headers，
            $passive = false, 被动的，消极的.true如果exchange存在则正常返回，不存在则关闭当前channel。
            $durable = false, 持久化，存入磁盘。
            $auto_delete = true, 前提必须是至少有一个与之绑定，之后所有与之绑定的都自动解绑。 不能错误的理解为：自动删除
            $internal = false,   是否内置，如果内置就不能通过客户端直接写入，必须通过内部的exchange->exchange的方式
            $nowait = false, 是否返回服务消息，如果true则不返回，false返回。
            $arguments = null,
            $ticket = null
            */
        try {
            $this->channel->exchange_declare($exchangeName, $exchangeType, false, true, false, false, false, $arguments);
        } catch (Exception $e) {
            throw new UnknowException($e->getMessage());
        }
    }

    final public function queueBind($queueName, $exchangeName, $routingKey = '', $arguments = [])
    {
        if (empty($queueName) || empty($exchangeName)) {
            throw new MissPropertyException('Missing necessary parameters: queueName or exchangeName');
        }
        if (!empty($arguments)) {
            $arguments = new AMQPTable($arguments);
        }
        /**
         * queueName 队列名称
         * exchangeName 路由名称
         * routingKey 路由键
         * noWait 是否返回服务消息，如果true则不返回，false返回。返回的是queue.bind_ok字符串
         * arguments
         * ticket
         */
        try {
            $this->channel->queue_bind($queueName, $exchangeName, $routingKey, true, $arguments);
        } catch (Exception $e) {
            throw new UnknowException($e->getMessage());
        }
    }

    /**
     * 发送消息
     *
     * @param JobInterface $job
     * @param string $routingKey
     * @return void
     */
    public function push($job, $routingKey = '')
    {
        $exchangeName = $this->exchangeName;
        if (empty($routingKey)) $routingKey = $this->routingKey;
        $event = new PushEvent([
            'job' => $job,
            'exchangeName' => $exchangeName,
            'routingKey' => $routingKey,
        ]);

        $this->trigger(self::EVENT_BEFORE_PUSH, $event);
        $id = $this->pushMessage($event->job, $exchangeName, $routingKey, $event->noWait);
        $event->id = $id;

        $this->trigger(self::EVENT_AFTER_PUSH, $event);

        return $event->id;
    }

    /**
     * 推送消息
     *
     * @param AmqpJob $job
     * @param string $exchangeName
     * @param string $routingKey
     * @return string
     */
    final protected function pushMessage($job, $exchangeName, $routingKey, $noWait = false)
    {
        $this->open();
        $message = $this->serializer->serialize($job);

        $this->channel->set_nack_handler(function (AMQPMessage $payload) {
            $job = $this->serializer->unserialize($payload->getBody());
            $event = new PushEvent(['job' => $job]);
            $this->trigger(self::EVENT_PUSH_NACK, $event);
        });

        $this->channel->set_ack_handler(function (AMQPMessage $payload) {
            $job = $this->serializer->unserialize($payload->getBody());
            $event = new PushEvent(['job' => $job]);
            $this->trigger(self::EVENT_PUSH_ACK, $event);
        });

        $this->channel->confirm_select($noWait);
        $id = uniqid('', true);
        $this->channel->basic_publish(
            new AMQPMessage($message, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $id,
                'priority' => $job->getPriority(),
            ]),
            $exchangeName,
            $routingKey
        );

        $this->channel->wait_for_pending_acks();
        return $id;
    }

    /**
     * 将待发送的消息先存入数组，然后将这组数据在通过publishBatch发送到AMQP Serve
     *
     * @param AaqpJob $job
     * @param string $exchangeName
     * @param string $routingKey
     * @return void
     */
    public function batchBasicPublish($job, $exchangeName, $routingKey)
    {
        $id = uniqid('', true);
        $message = $this->serializer->serialize($job);
        $this->channel->batch_basic_publish(
            new AMQPMessage($message, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $id,
                'priority' => $job->getPriority(),
            ]),
            $exchangeName,
            $routingKey
        );
    }

    /**
     * 将待发送数组内的消息发送到AMQP服务器
     *
     * @param PushEvent $event
     * @return void
     */
    public function publishBatch($noWait = false)
    {
        $this->channel->set_nack_handler(function (AMQPMessage $payload) {
            $job = $this->serializer->unserialize($payload->getBody());
            $event = new PushEvent(['job' => $job]);
            $this->trigger(self::EVENT_PUSH_NACK, $event);
        });

        $this->channel->set_ack_handler(function (AMQPMessage $payload) {
            $job = $this->serializer->unserialize($payload->getBody());
            $event = new PushEvent(['job' => $job]);
            $this->trigger(self::EVENT_PUSH_ACK, $event);
        });

        $this->channel->confirm_select($noWait);
        $this->channel->publish_batch();
        $this->channel->wait_for_pending_acks();
    }

    /**
     * @param string $queueName
     * @param integer $qos              //每个队列最多未被消费的条数
     * @param string $consumerTag       //消费者的标签
     */
    public function consume($queueName, $qos = 1, $consumerTag = '')
    {
        $this->open();
        $callback = function (AMQPMessage $payload) {
            $this->handleMessage($payload);
        };

        /*
         * prefetch_size 消费者所能接受未确认消息的总体大小
         * prefetch_count  所能接受最大的未确认条数
         * a_global
         */
        $this->channel->basic_qos(null, $qos, null);
        $this->channel->basic_consume($queueName, $consumerTag, false, false, false, false, $callback);
        // Loop as long as the channel has callbacks registered
        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }

    /**
     * 处理消息
     *
     * @param AMQPMessage $payload
     * @return ExecEvent
     */
    public function handleMessage(AMQPMessage $payload)
    {
        $job = $this->serializer->unserialize($payload->getBody());

        if (!($job instanceof AmqpJob)) {
            $payload->nack(false);
            return false;
        }

        $event = new ExecEvent(['job' => $job]);

        $this->trigger(self::EVENT_BEFORE_EXEC, $event);

        try {
            $event->result = $event->job->execute();
            if ($event->result !== false) {
                $payload->ack(true);
            } else {
                $payload->nack(true, true);
            }
        } catch (\Exception $error) {
            $event->error = $error;
        } catch (\Throwable $error) {
            $event->error = $error;
        }
        $this->trigger(self::EVENT_AFTER_EXEC, $event);
        return $event;
    }

    /**
     * 优先级队列设置
     *
     * @param int $priority
     * @return void
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * Opens connection and channel.
     */
    protected function open()
    {
        if ($this->channel) {
            return;
        }
        $this->connection = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->password, $this->vhost);
        $this->channel = $this->connection->channel();
    }

    /**
     * Closes connection and channel.
     */
    protected function close()
    {
        if (!$this->channel) {
            return;
        }
        $this->channel->close();
        $this->connection->close();
    }

    /**
     * 检查队列是否已经被绑定
     * @param string $queueName
     * @return bool
     */
    protected function isQueueCreated($queueName = '')
    {
        if (false === $this->strict) return false;
        if (empty($this->api)) {
            $this->api = new AmqpApi([
                'vhost' => $this->vhost,
                'host' => $this->host,
                'user' => $this->user,
                'password' => $this->password,
            ]);
            // throw new InvalidArgumentException('the object of api is null');
        }
        if (empty($queueName)) $queueName = $this->queueName;
        try {
            $info = $this->api->getBinding($queueName);
        } finally {
            // 该队列未被定义
            if (is_array($info) && empty($info)) return false;
            // 已被定义或防止某次HTTP请求失败造成的篡改
            return true;
        }
    }

    public function setApi($config) {
        if (isset($config['component']) && !empty($config['component'])) {
            $component = $config['component'];
            $this->api = Instance::ensure($component);
            return;
        }

        if (empty($config['class'])) {
            return false;
        }
        $class = $config['class'];
        unset($config['class']);

        $config['vhost'] = $this->vhost;
        $config['user'] = $this->user;
        $config['password'] = $this->password;
        $config['host'] = $this->host;
        $config['port'] = isset($config['port']) ? $config['port'] : 15672;
        $policyConfig = isset($config['policyConfig']) ? $config['policyConfig'] : [];
        unset($config['policyConfig']);
        
        $api = new $class($config);
        if (!($api instanceof AmqpApi)) {
            throw new InvalidArgumentException('invalid object of: api');
        }
        if ($api instanceof Policy && !empty($policyConfig)) {
            $api->setPolicyConfig($policyConfig);
            $api->setPolicy();
        }
        $this->api = $api;
    }

    public function getApi() {
        return $this->api;
    }

    /**
     * 删除队列
     *
     * @param [type] $queue
     * @return void
     */
    // final public function queueDelete($queueName)
    // {
    //     if (empty($queueName)) {
    //         throw new MissPropertyException('Missing necessary parameters of queueName');
    //     }
    //     $this->open();
    //     /*
    //      * if_unused 校验是否正在使用，如果为true则在使用的时候无法删除
    //      * if_empty  校验是否为空队列，如果为true则队列不为空无法删除
    //      */
    //     try {
    //         $this->channel->queue_delete($queueName, $if_unused = true, $if_empty = true);
    //     } catch (Exception $e) {
    //         throw new UnknowException($e->getMessage());
    //     }
    // }

    /**
     * 删除交换器
     *
     * @param [type] $exchange
     * @return void
     */
    // public function exchangeDelete($exchangeName)
    // {
    //     if (empty($exchangeName)) {
    //         throw new MissPropertyException('Missing necessary parameters of exchangeName');
    //     }
    //     $this->open();
    //     /*
    //      * if_unused 校验是否正在使用，如果为true则在使用的时候无法删除
    //      * if_empty  校验是否为空队列，如果为true则队列不为空无法删除
    //      */
    //     try {
    //         $this->channel->exchange_delete($exchangeName, $if_unused = true, $if_empty = true);
    //     } catch (Exception $e) {
    //         throw new UnknowException($e->getMessage());
    //     }
    // }
}
