<?php

namespace pzr\amqp\cli\Communication;

use pzr\amqp\Amqp;

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
        $this->amqp = new Amqp($config);
    }

    public function open()
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    public function prepareRead() {
        return true;
    }

    public function read()
    {
        $array = [];
        do {
            $job = $this->amqp->pop(self::PIPE);
            if (empty($job)) break;
            $array[] = $job->execute();
        } while(true);
        $this->logger->addLog(sprintf("[amqp] read buffer: %s", json_encode($array)));
        return $array;
    }

    public function write(string $queueName, int $qos)
    {
        if (empty($queueName) || empty($qos)) return false;
        $job = new CommunJob([$queueName, $qos]);
        $this->amqp->push($job);
        $this->logger->addLog(sprintf("[amqp] write: queueName:%s, qos:%d"), $job->queueName, $job->qos);
        return true;
    }

    public function write_batch(array $array)
    {
        $jobs = [];
        $string = [];
        foreach ($array as $a) {
            list($queueName, $qos) = $a;
            if (empty($queueName) || empty($qos)) continue;
            $jobs[] = new CommunJob([$queueName, $qos]);
            $string[] = $queueName.','.$qos;
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
