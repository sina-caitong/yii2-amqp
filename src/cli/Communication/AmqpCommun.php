<?php

namespace pzr\amqp\cli\Communication;

use pzr\amqp\Amqp;
use pzr\amqp\event\PushEvent;
use pzr\amqp\queue\EasyQueue;

class AmqpCommun extends BaseCommun
{
    private $amqp = null;
    private $logger = null;

    const PIPE = 'AMQP_PIPE';

    public function __construct(array $config)
    {
        parent::__construct($config);
        $config['queueName'] = self::PIPE;
        $config['exchangeName'] = self::PIPE;
        $this->amqp = new EasyQueue($config);
        $this->amqp->on(Amqp::EVENT_BEFORE_PUSH, function (PushEvent $event) {
            $event->noWait = true;
            $this->amqp->bind();
        });
    }

    public function open()
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    public function prepareRead()
    {
        return true;
    }

    public function read()
    {
        $array = [];
        do {
            $job = $this->amqp->pop(self::PIPE);
            if (empty($job)) break;
            $array[] = $job->execute();
        } while (true);
        $this->logger->addLog(sprintf("[amqp] read buffer: %s", json_encode($array)));
        return $array;
    }

    public function write(string $queueName, string $program)
    {
        if (empty($queueName) || empty($program)) return false;
        $job = new CommunJob([$queueName, $program]);
        $this->amqp->push($job);
        $this->logger->addLog(sprintf("[amqp] write: queueName:%s, program:%d"), $job->queueName, $job->program);
        return true;
    }

    public function write_batch(array $array)
    {
        $jobs = [];
        $string = [];
        foreach ($array as $a) {
            list($queueName, $program) = $a;
            if (empty($queueName) || empty($program)) continue;
            $jobs[] = new CommunJob([$queueName, $program]);
            $string[] = $queueName . ',' . $program;
        }
        if (empty($jobs)) return false;
        $this->amqp->publish($jobs);
        $this->logger->addLog(sprintf("[amqp] write:%s, len:%d", implode('|', $string), count($jobs)));
        return true;
    }

    public function flush()
    {
    }
}
