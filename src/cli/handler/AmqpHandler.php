<?php

namespace pzr\amqp\cli\handler;

use pzr\amqp\Amqp;
use pzr\amqp\cli\helper\ProcessHelper;
use pzr\amqp\event\PushEvent;
use pzr\amqp\queue\EasyQueue;

class AmqpHandler extends BaseHandler
{
    protected $amqp = null;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $config['exchangeName'] = self::QUEUE;
        $this->amqp = new EasyQueue($config);
        $this->amqp->on(Amqp::EVENT_BEFORE_PUSH, function(PushEvent $event) {
            $event->noWait = true;
            $this->amqp->bind();
        });
    }


    public function addQueue(int $pid, int $ppid, string $queue, string $program)
    {
        if (empty($pid) || empty($ppid) || empty($queue) || empty($program)) {
            return false;
        }
        $this->logger->addLog(sprintf("[%s] %s=%s,%s", self::EVENT_ADD_QUEUE, $pid, $queue, $program));
        $job = new ConsumerJob([
            'pid' => $pid,
            'ppid' => $ppid,
            'queueName' => $queue,
            'program' => $program,
            'event' => self::EVENT_ADD_QUEUE,
        ]);
        $this->amqp->push($job);
    }

    public function handle()
    {
        $this->amqp->consume(self::QUEUE, 1, '', true);
    }

    public function delPid(int $pid = 0, int $ppid = 0)
    {
        if (empty($pid) && empty($ppid)) return false;
        $pidInfo = ProcessHelper::readPid($pid, $ppid);
        $event = empty($pid) ? self::EVENT_DELETE_PPID : self::EVENT_DELETE_PID;
        $job = new ConsumerJob([
            'event' => $event,
            'pid' => $pid,
            'ppid' => $ppid,
        ]);
        $this->amqp->push($job);
        $this->logger->addLog(sprintf("[%s] %d_%d,  pidinfo:%s", $event, $pid, $ppid, json_encode($pidInfo)));
        return $pidInfo;
    }
}
