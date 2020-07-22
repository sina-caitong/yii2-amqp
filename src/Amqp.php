<?php


namespace pzr\amqp;

use Exception;
use pzr\amqp\duplicate\DuplicateInterface;
use pzr\amqp\duplicate\DuplicateRandom;
use pzr\amqp\event\PushEvent;
use pzr\amqp\exception\InvalidArgumentException;
use pzr\amqp\exception\UnknowException;
use yii\di\Instance;


class Amqp extends AmqpBase
{
    /** @var integer 队列的副本数量 */
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
     * 启动队列副本后自动申明多个队列
     * @param $queueName
     * @param array $arguments
     * @return mix
     */
    final public function queuesDeclare($queueName, array $arguments = [])
    {
        if (empty($queueName)) {
            throw new InvalidArgumentException('invalid value of: queueName');
        }

        if ($this->duplicate <= 1) {
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

    /**
     * 启用队列副本后自动绑定多个队列
     *
     * @param string $queueName
     * @param string $exchangeName
     * @param string $routingKey
     * @param array $arguments
     * @return void
     */
    final public function queuesBind($queueName, $exchangeName, $routingKey = '', $arguments = [])
    {
        if (empty($queueName) || empty($exchangeName)) {
            throw new InvalidArgumentException('invalid value of: queueName or exchangeName');
        }

        if ($this->duplicate <= 1) {
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
    public function publish(array $jobs)
    {
        $this->open();
        if (!is_array($jobs)) {
            return false;
        }

        // 放在循环里面是对每一条消息都随机路由，放在循环外则对这一批消息路由
        $routingKey = $this->duplicater->getRoutingKey($this->routingKey, $this->duplicate);
        foreach ($jobs as $job) {
            if (!($job instanceof JobInterface)) {
                continue;
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
    public function push($job)
    {
        $routingKey = $this->duplicater->getRoutingKey($this->routingKey, $this->duplicate);
        return parent::push($job, $routingKey);
    }

    /**
     * @inheritDoc
     * @return bool false可以被创建，true不能被创建
     */
    protected function isQueueCreated($queueName='')
    {
        if (false === $this->strict) return false;
        if ($this->duplicate <= 1) {
            return parent::isQueueCreated();
        }

        $queueName = $this->queueName;
        $duplicate = $this->duplicate;
        $i = 0;
        while ($duplicate) {
            $queueNameTmp = $queueName . '_' . $i;
            $flag = parent::isQueueCreated($queueNameTmp);
            if ($flag) return true;
            $i++;
            $duplicate--;
        }
        return false;
    }
}
