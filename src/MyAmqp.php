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

    public function init()
    {
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
            $this->queueDeclare($queueName, $arguments);
            return;
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
            $this->queueBind($queueName, $exchangeName, $routingKey, $arguments);
            return;
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
                $this->queueBind($queueName . '_' . $i, $exchangeName, $routingKey . '_' . $i, $arguments);
            }
        } catch (Exception $e) {
            throw new UnknowException($e->getMessage());
        }
    }

    /**
     * 消息分成一组发送
     *
     * @param array $jobs
     * @param string $routingKey
     * @return void
     */
    public function myPublishBatch(array $jobs, $routingKey = '')
    {
        $this->open();
        if (!is_array($jobs)) {
            throw new InvalidArgumentException('jobs is not a array');
        }

        // 放在循环里面是对每一条消息都随机路由，放在循环外则对这一批消息路由
        if (empty($routingKey)) {
            $routingKey = $this->routingKey;
        }
        $routingKey = $this->duplicater->getRoutingKey($routingKey, $this->duplicate);
        foreach ($jobs as $job) {
            if (!($job instanceof JobInterface)) {
                throw new InvalidArgumentException('job is not implatement JobInterface');
            }
            $this->batchBasicPublish($job, $this->exchangeName, $routingKey);
        }
        $event = new PushEvent([
            'jobs' => $jobs,
            'exchangeName' => $this->exchangeName,
            'routingKey' => $routingKey,
        ]);
        $this->trigger(self::EVENT_BEFORE_PUSH, $event);
        $this->publishBatch($event->noWait);
        $this->trigger(self::EVENT_AFTER_PUSH, $event);
    }

    /**
     * @inheritDoc
     */
    public function push($job, $routingKey = '')
    {
        if (empty($routingKey)) $routingKey = $this->routingKey;
        $routingKey = $this->duplicater->getRoutingKey($routingKey, $this->duplicate);
        return parent::push($job, $routingKey);
    }
}
