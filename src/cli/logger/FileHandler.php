<?php

namespace pzr\amqp\cli\logger;

use Monolog\DateTimeImmutable;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
use pzr\amqp\cli\helper\AmqpIni;

class FileHandler implements HandlerInterface
{
    private $access_log = '';
    private $error_log = '';
    /** @var array */
    private $level;

    private $real_access_log;
    private $real_error_log;

    public function __construct($access_log, $error_log, array $level = array())
    {
        $this->access_log = $access_log;
        $this->error_log = $error_log;
        $this->level = $level;
    }

    

    public function isHandling(array $record): bool
    {
        $level = $record['level'];
        if (!empty($this->level) && !in_array($level, $this->level)) return false;
        if (empty($this->access_log) && empty($this->error_log)) {
            return false;
        }

        if (preg_match('/%Y|%y|%d|%m/', $this->access_log)) {
            $this->real_access_log = str_replace(['%Y', '%y', '%m', '%d'], [date('Y'), date('y'), date('m'), date('d')], $this->access_log);
            $this->real_access_log = AmqpIni::findRealpath($this->real_access_log);
        }

        if (preg_match('/%Y|%y|%d|%m/', $this->error_log)) {
            $this->real_error_log = str_replace(['%Y', '%y', '%m', '%d'], [date('Y'), date('y'), date('m'), date('d')], $this->error_log);
            $this->real_error_log = AmqpIni::findRealpath($this->real_error_log);
        }

        $access_log = $this->real_access_log ?: $this->access_log;
        $error_log = $this->real_error_log ?: $this->error_log;
        
        if (empty($access_log) || empty($error_log)) return false;
        return true;
    }

    public function handle(array $record): bool
    {
        if (empty($record)) return true;
        $access_log = $this->real_access_log ?: $this->access_log;
        $error_log = $this->real_error_log ?: $this->error_log;
        $level = $record['level'];
        switch ($level) {
            case Logger::DEBUG:
            case Logger::INFO:
            case Logger::WARNING:
            case Logger::NOTICE:
                if (empty($access_log) || !is_file($access_log)) return false;
                //~v2.0.0 为了满足开发机的5.6版本，降低了monolog的版本
                // $content = sprintf("%s【%s】%s", $record['datetime'], $record['level_name'], $record['message']);
                // v1.25.5 出现错误，$record['datetime']->date 输出了对象属性date却无法获取
                // $content = sprintf("%s【%s】%s", $record['datetime']->date, $record['level_name'], $record['message']);
                $content = sprintf("%s【%s】%s", date('Y-m-d H:i:s'), $record['level_name'], $record['message']);
                @error_log($content . PHP_EOL, 3, $access_log);
                break;
            case Logger::ERROR:
            case Logger::CRITICAL:
                if (empty($error_log) || !is_file($error_log)) return false;
                $content = sprintf("%s【%s】%s", date('Y-m-d H:i:s'), $record['level_name'], $record['message']);
                @error_log($content . PHP_EOL, 3, $error_log);
                break;
            // 邮件提醒
            case Logger::ALERT:
            case Logger::EMERGENCY:
                break;
        }
        return true;
    }

    public function close(): void
    {
    }

    public function pushProcessor($callback)
    {
        
    }

    public function popProcessor()
    {
        
    }

    public function getFormatter()
    {
        
    }

    public function setFormatter(FormatterInterface $formatter)
    {
        
    }

    public function handleBatch(array $records): void
    {
    }

    /**
     * Get the value of real_access_log
     */ 
    public function getReal_access_log()
    {
        return $this->real_access_log;
    }

    /**
     * Get the value of real_error_log
     */ 
    public function getReal_error_log()
    {
        return $this->real_error_log;
    }
}
