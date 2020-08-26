<?php

namespace pzr\amqp\cli\handler;

use pzr\amqp\cli\helper\ProcessHelper;

class RedisHandler extends BaseHandler
{
    protected $redis = null;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->redis = new \Redis();
        $this->redis->connect($config['host'], $config['port']);
        $this->redis->auth($config['password']);
    }

    public function addQueue(int $pid, int $ppid, string $queue, string $program)
    {
        if (empty($pid) || empty($ppid) || empty($queue) || empty($program)) {
            return false;
        }
        $this->logger->addLog(sprintf("[%s] %s=%s,%s", self::EVENT_ADD_QUEUE, $pid, $queue, $program));
        $job = [self::EVENT_ADD_QUEUE => [$pid, $ppid, $queue, $program]];
        $this->redis->lPush(self::QUEUE, json_encode($job));
    }

    public function handle()
    {
        while(true) {
            $data = $this->redis->brPop(self::QUEUE, 1);
            if (empty($data)) continue;
            list($key, $json) = $data;
            $data = json_decode($json, true);
            $this->write($data);
        }
    }

    public function delPid(int $pid = 0, int $ppid = 0)
    {
        if (empty($pid) && empty($ppid)) return false;
        $pidInfo = ProcessHelper::readPid($pid, $ppid);
        $event = empty($pid) ? self::EVENT_DELETE_PPID : self::EVENT_DELETE_PID;
        $deadPid = empty($pid) ? $ppid : $pid;
        $job = [$event => $deadPid];
        $this->redis->lPush(self::QUEUE, json_encode($job));
        $this->logger->addLog(sprintf("[%s] %d_%d,  pidinfo:%s", $event, $pid, $ppid, json_encode($pidInfo)));
        return $pidInfo;
    }

    public function write($body)
    {
        if (empty($body) || !is_array($body)) return false;
        $event = key($body);
        $data = $body[$event];
        switch ($event) {
            case HandlerInterface::EVENT_ADD_QUEUE:
                list($pid, $ppid, $queueName, $program) = $data;
                ProcessHelper::handleAddQueue($pid, $ppid, $queueName, $program);
                break;
            case HandlerInterface::EVENT_DELETE_PID:
                ProcessHelper::handleDelPid($data);
                break;
            case HandlerInterface::EVENT_DELETE_PPID:
                ProcessHelper::handleDelPpid($data);
                break;
        }
    }
}
