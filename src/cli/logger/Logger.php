<?php


namespace pzr\amqp\cli\logger;

use DateTimeZone;
use Monolog\Logger as BaseLogger;

class Logger
{

    protected $logger = null;
    public function __construct($access_log, $error_log='', string $levelString='')
    {
        $this->logger = new BaseLogger('AMQP-CONSUMER');
        $this->logger->setTimezone(new DateTimeZone('Asia/Shanghai'));
        $this->logger->useMicrosecondTimestamps(false);

        $levels = BaseLogger::getLevels();
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

    public function addLog($message, $level = BaseLogger::INFO)
    {
        if (is_array($message)) {
            $str = PHP_EOL . '';
            foreach ($message as $k => $v) {
                if (empty($v)) continue;
                if (is_string($v)) {
                    $str .= "$k=>$v" . PHP_EOL;
                    continue;
                }
                foreach ($v as $kk => $vv) {
                    $str .= "$k.$kk=>$vv" . PHP_EOL;
                }
            }
            $message = $str;
        }
        $this->logger->addRecord($level, $message);
    }
}
