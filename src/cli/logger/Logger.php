<?php


namespace pzr\amqp\cli\logger;

use DateTimeZone;
use Monolog\Logger as LoggerBase;

class Logger
{

    protected $logger = null;
    public function __construct($access_log, $error_log='', string $levelString='')
    {
        $this->logger = new LoggerBase('AMQP-CONSUMER');
        $this->logger->setTimezone(new DateTimeZone('Asia/Shanghai'));
        $this->logger->useMicrosecondTimestamps(false);

        $levels = LoggerBase::getLevels();
        $levelArr = empty($levelString) ? array() : explode(',', $levelString);
        $level = array();
        foreach($levelArr as $name) {
            $name = strtoupper($name);
            if (!isset($levels[$name])) continue;
            $level[] = $levels[$name];
        }

        $this->logger->pushHandler(
            new FileHandler($access_log, $error_log, $level)
        );
    }

    public function addLog($message, $level = LoggerBase::INFO)
    {
        $this->logger->addRecord($level, $message);
    }
}
