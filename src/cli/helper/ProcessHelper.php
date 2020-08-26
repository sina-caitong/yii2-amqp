<?php

namespace pzr\amqp\cli\helper;

use Monolog\Logger;
use pzr\amqp\cli\handler\HandlerInterface;
use pzr\amqp\cli\helper\SignoHelper;

class ProcessHelper
{


    public static $array = array();
    public static $programs = array();
    /**
     * 获取进程的信息，用于某个子进程死亡的时候可以重启。
     * 但是如果该子进程的父进程被kill -9 杀死，那么这个进程的父进程ID在linux中会变成1
     * 而pidfile中保存的父进程ID可能会被重写或者无法接收到reload的信号
     * 最终达到的效果是：父进程还在的可以reload，父进程已经死了的会被杀死
     *
     * @param int $pid
     * @param int $ppid
     * @return array|bool
     */
    public static function readPid(int $pid, int $ppid)
    {
        if (empty($pid) || empty($ppid)) return false;
        $array = self::read();
        $key = $pid . '_' . $ppid;
        if (isset($array[$key]) && $array[$key]) {
            list($pid, $ppid, $queueName, $program) = $array[$key];
            return [$queueName, $program];
        }
        return false;
    }

    /** @return array */
    public static function read()
    {
        $array = parse_ini_file(AmqpIni::getProcessFile());
        $databack = [];
        foreach ($array as $k => $v) {
            list($pid, $ppid) = explode('_', $k);
            list($queueName, $program) = explode(',', $v);
            $databack[$k] = [
                intval($pid), intval($ppid), $queueName, $program
            ];
        }
        return $databack;
    }

    public static function getPrograms() {
        $array = self::read();
        $databack = [];
        foreach($array as $v) {
            list($pid, $ppid, $queueName, $program) = $v;
            $key = self::getConsumerKey($queueName, $program);
            $databack[$key] = $program;
        }
        return $databack;
    }

    public static function getConsumerKey($queueName, $program) {
        return md5($queueName . $program);
    }

    public static function isProcessExit($queueName, $program) {
        $key = self::getConsumerKey($queueName, $program);
        $programs = self::getPrograms();
        return isset($programs[$key]) ? true : false;
    }

    public static function write(string $string, $append = false)
    {
        $flag = $append ? FILE_APPEND : 0;
        return file_put_contents(AmqpIni::getProcessFile(), $string, $flag);
    }

    public static function flush()
    {
        return self::write('');
    }

    /** @var array|string|int */
    public static function handleAddQueue($pid, $ppid, $queueName, $program)
    {
        $ini = sprintf("%d_%d = %s,%s%s", $pid, $ppid, $queueName, $program, PHP_EOL);
        return self::write($ini, true);
    }

    /** @var int */
    public static function handleDelPid($deadPid)
    {
        $array = ProcessHelper::read();
        $ini = '';
        foreach ($array as $v) {
            list($pid, $ppid, $queueName, $program) = $v;
            if ($pid == $deadPid) {
                AmqpIni::addLog(sprintf("[handle %s] %d", HandlerInterface::EVENT_DELETE_PID, $pid));
                continue;
            }
            $ini .= sprintf("%d_%d = %s,%s%s", $pid, $ppid, $queueName, $program, PHP_EOL);
        }
        return self::write($ini);
    }

    /** @var int */
    public static function handleDelPpid($domain)
    {
        $array = ProcessHelper::read();
        foreach ($array as $v) {
            list($pid, $ppid, $queueName, $program) = $v;
            AmqpIni::addLog(sprintf("[handle %s] %d", HandlerInterface::EVENT_DELETE_PPID, $ppid));
            posix_kill($pid, SignoHelper::KILL_CHILD_STOP);
        }
        return self::flush();
    }

    public static function killAll()
    {
        $array = self::read();
        foreach ($array as $v) {
            list($pid, $ppid, $queueName, $program) = $v ;
            $res = posix_kill($pid, SignoHelper::KILL_CHILD_STOP);
            AmqpIni::addLog(sprintf("[flush] kill -%d %d ,result is %s", SignoHelper::KILL_CHILD_STOP, $pid, $res));
        }

        return self::flush();
    }
}
