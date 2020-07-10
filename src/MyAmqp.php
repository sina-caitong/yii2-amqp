<?php


namespace pzr\amqp;

use Exception;
use pzr\amqp\duplicate\DuplicateInterface;
use pzr\amqp\duplicate\DuplicateRandom;
use pzr\amqp\event\PushEvent;
use pzr\amqp\exception\InvalidArgumentException;
use pzr\amqp\exception\MissPropertyException;
use pzr\amqp\exception\UnknowException;
use yii\di\Instance;

class MyAmqp extends AmqpBase
{

    /**
     * 队列的副本数量
     *
     * @var integer
     */
    public $duplicate = 0;

    /**
     * @var DuplicateInterface|array
     */
    public $duplicater = DuplicateRandom::class;

    public function init() {
        parent::init();
        $this->duplicater = Instance::ensure($this->duplicater, DuplicateInterface::class);
    }


    /**
     * 为了优化flow，实例化多个queue同时处理对应的exchange，从而达到高的吞吐量
     * @param $queueName
     * @param array $arguments
     * @return mix
     */
    final public function queuesDeclare($queueName, array $arguments = [])
    {
        if (empty($queueName)) {
            throw new MissPropertyException('Missing necessary parameters of queueName');
        }

        if ($this->duplicate < 1) {
            throw new MissPropertyException('The number of queue duplicate must be greater than one');
        }

        try {
            for ($i = 0; $i < $this->duplicate; $i++) {
                $queueNameTmp = $queueName . '_' . $i;
                $this->queueDeclare($queueNameTmp, $arguments);
            }
        } catch (Exception $e) {
            throw new UnknowException($e->getMessage());
        }
    }

    final public function queuesBind($queueName, $exchangeName, $routingKey = '', $arguments = [])
    {
        if (empty($queueName) || empty($exchangeName) || empty($routingKey)) {
            throw new MissPropertyException('Missing necessary parameters of queueName or exchangeName or routingKey');
        }

        if ($this->duplicate < 1) {
            throw new MissPropertyException('The number of queue duplicate must be greater than one');
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
            for ($i = 0; $i < $this->duplicate; $i++) {
                $this->queueBind($queueName . '_' .$i, $exchangeName, $routingKey . '_' . $i, $arguments);
            }
        } catch (Exception $e) {
            throw new UnknowException($e->getMessage());
        }
    }

    /**
     * 消息分成一组发送
     *
     * @param array $jobs
     * @param [type] $exchangeName
     * @param [type] $routingKey
     * @return void
     */
    final public function myPublishBatch(array $jobs)
    {
       $this->myOpen();
        if (!is_array($jobs)) {
            throw new MissPropertyException('jobs is not a array');
        }

        foreach ($jobs as $job) {
            $routingKey = $this->duplicater->getRoutingKey($this->routingKey, $this->duplicate);
            $this->batchBasicPublish($job, $this->exchangeName, $routingKey);
        }
        $event = new PushEvent([
            'jobs' => $jobs,
            'exchangeName' => $this->exchangeName,
            'routingKey' => $routingKey,
        ]);
        $this->trigger(self::EVENT_BEFORE_PUSH, $event);
        $this->publishBatch();
        $this->trigger(self::EVENT_AFTER_PUSH, $event);
    }

    /**
     * @inheritDoc
     */
    public function push($job, $routingKey='')
    {
        $routingKey = $this->duplicater->getRoutingKey($this->routingKey, $this->duplicate);
        return parent::push($job, $routingKey);
    }
}
