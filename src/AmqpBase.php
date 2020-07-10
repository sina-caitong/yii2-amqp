<?php

namespace pzr\amqp;

use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use pzr\amqp\event\ExecEvent;
use pzr\amqp\event\PushEvent;
use pzr\amqp\exception\MissPropertyException;
use pzr\amqp\exception\UnknowException;
use pzr\amqp\lib\AMQPConnection;
use pzr\amqp\QueueInterface;
use pzr\amqp\serializers\SerializerInterface;
use pzr\amqp\serializers\PhpSerializer;
use yii\base\Component;
use yii\base\Event;
use yii\base\Application as BaseApp;
use yii\di\Instance;

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

    protected $channel;
    protected $connection;
    /**
     * 定义队列的优先级
     *
     * @var int
     */
    public $priority;

    /**
     * @var SerializerInterface|array
     */
    public $serializer = PhpSerializer::class;

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
     * @param [type] $queueName
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
     * Direct exchange: 如果 routing key 匹配, 那么Message就会被传递到相应的queue中。其实在queue创建时，它会自动的以queue的名字作为routing key来绑定那个exchange。
     * Fanout exchange: 会向响应的queue广播。
     * Topic exchange: 对key进行模式匹配，比如ab*可以传递到所有ab*的queue。
     * @param $exchangeName
     * @param $exchangeType
     * @param array $arguments
     * @return bool|mixed|null
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
        $id = $this->pushMessage($event->job, $exchangeName, $routingKey, $event);
        $event->id = $id;

        $this->trigger(self::EVENT_AFTER_PUSH, $event);

        return $event->id;
    }

    /**
     * 推送消息
     *
     * @param JobInterface $job
     * @param string $exchangeName
     * @param string $routingKey
     * @return string
     */
    final protected function pushMessage($job, $exchangeName, $routingKey, $event)
    {
        $this->myOpen();
        $message = $this->serializer->serialize($job);
        $id = uniqid('', true);
        $this->channel->set_nack_handler(function (AMQPMessage $payload) use ($exchangeName, $routingKey) {
            $event = new PushEvent([
                'job' => $payload->getBody(),
                'exchangeName' => $exchangeName,
                'routingKey' => $routingKey,
            ]);
            $this->trigger(self::EVENT_PUSH_NACK, $event);
        });

        $this->channel->set_ack_handler(function (AMQPMessage $payload) use ($exchangeName, $routingKey) {
            $event = new PushEvent([
                'job' => $payload->getBody(),
                'exchangeName' => $exchangeName,
                'routingKey' => $routingKey,
            ]);
            $this->trigger(self::EVENT_PUSH_ACK, $event);
        });

        $this->channel->confirm_select($event->noWait);
        $this->channel->basic_publish(
            new AMQPMessage($message, [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $id,
                'priority' => $job->getPriority() ?: 0,
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
     * @param \pzr\amqp\JobInterface $job
     * @param string $exchangeName
     * @param string $routingKey
     * @return void
     */
    final public function batchBasicPublish($job, $exchangeName, $routingKey)
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
    final public function publishBatch($event)
    {
        $this->channel->set_nack_handler(function (AMQPMessage $payload) {
            $event = new PushEvent([
                'job' => $payload->getBody(),
            ]);
            $this->trigger(self::EVENT_PUSH_NACK, $event);
        });

        $this->channel->set_ack_handler(function (AMQPMessage $payload) {
            $event = new PushEvent([
                'job' => $payload->getBody(),
            ]);
            $this->trigger(self::EVENT_PUSH_ACK, $event);
        });

        $this->channel->confirm_select($event->noWait);
        $this->channel->publish_batch();
        $this->channel->wait_for_pending_acks();
    }



    

    /**
     * @param $queueName
     * @param $consumerTag      //消费者的标签
     * @param $qos              //每个队列最多未被消费的条数
     */
    public function consume($queueName, $qos = 1, $consumerTag = '')
    {
        $this->open();
        $callback = function (AMQPMessage $payload) use ($queueName) {
            $this->handleMessage($payload, $queueName);
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
     * @param string $queueName
     * @return bool
     */
    protected function handleMessage(AMQPMessage $payload, $queueName)
    {
        $message = $payload->getBody();
        $job = $this->serializer->unserialize($message);

        if (!($job instanceof JobInterface)) {
            return false;
        }

        $event = new ExecEvent([
            'job' => $job,
            'queueName' => $queueName,
        ]);

        $this->trigger(self::EVENT_BEFORE_EXEC, $event);

        try {
            $event->result = $event->job->execute();
            if ($event->result) {
                $this->channel->basic_ack($payload->delivery_info['delivery_tag']);
            } else {
                $this->channel->basic_nack($payload->delivery_info['delivery_tag']);
            }
        } catch (\Exception $error) {
            $event->error = $error;
        } catch (\Throwable $error) {
            $event->error = $error;
        }
        $this->trigger(self::EVENT_AFTER_EXEC, $event);
        return true;
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
     * 为了处理生产者消息确认模式，修改代码如：
     * 
     * if ($this->next_delivery_tag == 0) //my eidt
     *    $this->next_delivery_tag = 1;
     * 
     */
    protected function myOpen()
    {
        if ($this->channel) {
            return;
        }
        $this->connection = new AMQPConnection($this->host, $this->port, $this->user, $this->password, $this->vhost);
        $this->channel = $this->connection->channel();
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
