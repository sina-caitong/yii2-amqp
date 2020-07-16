<?php

namespace pzr\amqp;

use Exception;
use PhpAmqpLib\Message\AMQPMessage;
use pzr\amqp\event\ExecEvent;
use pzr\amqp\event\PushEvent;
use pzr\amqp\exception\InvalidArgumentException;
use pzr\amqp\exception\RpcException;
use pzr\amqp\exception\UnknowException;

class RpcAmqp extends MyAmqp
{
    /** @var string 临时队列名称 */
    private $_callbackQueueName;

    /** @var array 临时记录所有请求的correlation_id */
    private $_corrids = array();

    /** @var array 返回的响应体 */
    private $_responses = array();

    /** @var int 临时队列消费者的QOS */
    private $_qos = 1;

    /** @var int 临时队列消费者的超时时间，单位seconds */
    private $_timeout = 10;

    /**
     * 单条消息处理
     *
     * @param AmqpJob $job 
     * @return void
     */
    public function push($job, $routingKey='')
    {
        $this->open();

        $this->trigger(self::EVENT_BEFORE_PUSH, new PushEvent(['job'=>$job]));

        list($this->_callbackQueueName,,) = $this->channel->queue_declare("", false, false, true, false);
        if (empty($this->_callbackQueueName)) {
            throw new RpcException('callbackQueueName is empty');
        }
        $message = $this->serializer->serialize($job);
        $corrid = $job->getUuid();
        $this->myLog('请求唯一ID：' . $corrid . '; 临时队列名称：' . $this->_callbackQueueName);
        $payload = new AMQPMessage($message, [
            'correlation_id' => $corrid,
            'reply_to' => $this->_callbackQueueName,
        ]);
        if (empty($routingKey)) $routingKey = $this->routingKey;
        $this->channel->basic_publish($payload, $this->exchangeName, $routingKey);
        $this->trigger(self::EVENT_AFTER_PUSH, new PushEvent(['job'=>$job]));

        $this->channel->basic_qos(null, 1, null);
        $this->channel->basic_consume($this->_callbackQueueName, '', false, false, false, false, array($this, 'handleResponse'));
        try {
            while (empty($this->_responses[$corrid])) {
                $this->channel->wait(null, false, $this->_timeout);
            }
        } catch (Exception $e) {
            throw new UnknowException($e->getMessage());
        } finally {
            // 必须返回的是对象才能够返回到请求方，否则会被Yii底层转成int型
            return new Response([
                'response' => $this->_responses[$corrid]
            ]);
        }
    }

    /**
     * 批量消息处理
     *
     * @param array $jobs
     * @return void
     */
    public function myPublishBatch(array $jobs, $routingKey='')
    {
        $this->open();
        if (!is_array($jobs)) {
            throw new InvalidArgumentException('jobs is not a array');
        }
        $event = new PushEvent(['jobs'=>$jobs]);
        $this->trigger(self::EVENT_BEFORE_PUSH, $event);

        list($this->_callbackQueueName,,) = $this->channel->queue_declare("", false, false, true, false);
        if (empty($this->_callbackQueueName)) {
            throw new RpcException('callbackQueueName is empty');
        }
        $this->myLog('临时队列名称：' . $this->_callbackQueueName);

        if (empty($routingKey)) $routingKey = $this->routingKey;
        $routingKey = $this->duplicater->getRoutingKey($routingKey, $this->duplicate);
        foreach ($jobs as $job) {
            if (!($job instanceof AmqpJob)) {
                throw new InvalidArgumentException('job is not instanceof AmqpJob');
            }
            $this->_corrids[] = $job->getUuid();
            $this->batchBasicPublish($job, $this->exchangeName, $routingKey);
        }
        $this->publishBatch($event->noWait);

        $this->trigger(self::EVENT_AFTER_PUSH, $event);
        /** 即使可以通过一个channel启动多个消费者，但是消费者处理消息也不是并发处理 */
        $this->channel->basic_qos(null, $this->_qos, null);
        $this->channel->basic_consume($this->_callbackQueueName, '', false, false, false, false, array($this, 'handleResponse'));

        try {
            while (count($this->_corrids) != count($this->_responses)) {
                $this->channel->wait(null, false, $this->_timeout);
            }
        } catch (Exception $e) {
            throw new UnknowException($e->getMessage());
        } finally {
            // 必须返回的是对象才能够返回到请求方，否则会被Yii底层转成int型
            return new Response([
                'response' => $this->_responses
            ]);
        }
    }

    /**
     * 消费者处理临时队列的消息
     * 
     * @param AMQPMessage $payload
     * @return void
     */
    public function handleResponse(AMQPMessage $payload)
    {
        $corrid = $payload->get('correlation_id');
        $this->_responses[$corrid] = $payload->body;
        $payload->delivery_info['channel']->basic_ack(
            $payload->delivery_info['delivery_tag']
        );
        $this->myLog('临时队列处理消息ID：' . $corrid);
    }

    /**
     * @inheritDoc
     *
     * @param \pzr\amqp\AmqpJob $job
     * @param string $exchangeName
     * @param string $routingKey
     * @return void
     */
    public function batchBasicPublish($job, $exchangeName, $routingKey)
    {
        $message = $this->serializer->serialize($job);
        $this->channel->batch_basic_publish(
            new AMQPMessage($message, [
                'correlation_id' => $job->getUuid(),
                'reply_to' => $this->_callbackQueueName,
            ]),
            $exchangeName,
            $routingKey
        );
    }

    /**
     * RPC的消费者处理消息
     * 
     * @inheritDoc
     * @param AMQPMessage $payload
     * @return ExecEvent
     */
    public function handleMessage(AMQPMessage $payload)
    {
        $this->myLog('RPC队列处理消息ID：' . $payload->get('correlation_id'));
        $event = parent::handleMessage($payload);

        if ($event->result !== false) {
            $this->pushResponse($payload, $event->result);
        }

        return $event;
    }

    /**
     * 将响应的内容推到临时队列
     *
     * @param AMQPMessage $payload
     * @param ExecEvent $event
     * @return void
     */
    public function pushResponse($payload, $response)
    {
        $response = $this->serializer->serialize($response);
        if (empty($payload->get('correlation_id'))) {
            throw new InvalidArgumentException('correlation_id is empty');
        }
        $message = new AMQPMessage(
            $response,
            array('correlation_id' => $payload->get('correlation_id'))
        );

        $payload->delivery_info['channel']->basic_publish(
            $message,
            '',
            $payload->get('reply_to')
        );
    }

    /**
     * Set the value of _qos
     *
     * @return  self
     */
    public function setQos($_qos)
    {
        $this->_qos = $_qos;
        return $this;
    }

    /**
     * Set the value of _timeout
     *
     * @return  self
     */
    public function setTimeout($_timeout)
    {
        $this->_timeout = $_timeout;
        return $this;
    }

     /**
     * 批量请求的时候返回多个响应
     *
     * @return void
     */
    public function getResponses()
    {
        return $this->_responses;
    }
}
