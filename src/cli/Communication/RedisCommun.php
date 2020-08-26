<?php

namespace pzr\amqp\cli\Communication;

use Monolog\Logger as BaseLogger;

class RedisCommun extends BaseCommun
{
    private $redis = null;
    private $logger = null;

    const PIPE = 'AMQP_PIPE';

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->redis = new \Redis();
        $this->redis->connect($config['host'], $config['port']);
        $this->redis->auth($config['password']);
    }

    public function open()
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read()
    {
        $array = [];
        do {
            $buffer = $this->redis->rPop(self::PIPE);
            if (empty($buffer)) break;
            $array[] = explode(',', $buffer);
        } while(true);
        $this->logger->addLog(sprintf("[redis] read buffer: %s", json_encode($array)));
        return $array;
    }

    public function write(string $queueName, string $program)
    {
        if (empty($queueName) || empty($program)) return false;
        $string = $queueName . ',' . $program;
        $len = $this->redis->lPush(self::PIPE, $string);
        $level = $len ? BaseLogger::INFO : BaseLogger::WARNING;
        $this->logger->addLog(sprintf("[redis] write:%s, len:%d", $string, $len), $level);
        return $len;
    }

    public function write_batch(array $array)
    {
        $strings = [];
        foreach ($array as $a) {
            if (empty($a['queueName']) || empty($a['program'])) continue;
            $strings[] = $a['queueName'] . ',' . $a['program'];
        }
        if (empty($strings)) return false;
        $string = implode('|', $strings);
        $len = $this->redis->lPush(self::PIPE, $string);
        $level = $len ? BaseLogger::INFO : BaseLogger::WARNING;
        $this->logger->addLog(sprintf("[redis] write:%s, len:%d, succ:%d", $string, $len), $level);
        return $len;
    }

    public function flush()
    {
        
    }
}
