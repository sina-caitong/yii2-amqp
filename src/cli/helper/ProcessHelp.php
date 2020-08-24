<?php

namespace pzr\amqp\cli\helper;

use pzr\amqp\cli\handler\HandlerInterface;
use pzr\amqp\cli\helper\SignoHelper;

class ProcessHelper
{


    const DEFAULT_PROCESS_MANAGER_PATH = __DIR__ . '/../config/process_manager.ini';

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
            list($pid, $ppid, $queueName, $qos) = $array[$key];
            return [$queueName, $qos];
        }
        return false;
    }

    /** @return array */
    public static function read()
    {
        $array = parse_ini_file(self::DEFAULT_PROCESS_MANAGER_PATH);
        $databack = [];
        foreach ($array as $k => $v) {
            list($pid, $ppid) = explode('_', $k);
            list($queueName, $qos) = explode(',', $v);
            $databack[$k] = [
                intval($pid), intval($ppid), $queueName, $qos
            ];
        }
        return $databack;
    }

    public static function write(string $string, $append = false)
    {
        $flag = $append ? FILE_APPEND : 0;
        return file_put_contents(self::DEFAULT_PROCESS_MANAGER_PATH, $string, $flag);
    }

    public static function flush()
    {
        return self::write('');
    }

    /** @var array|string|int */
    public static function handleAddQueue($pid, $ppid, $queueName, $qos)
    {
        $ini = sprintf("%d_%d = %s,%d%s", $pid, $ppid, $queueName, $qos, PHP_EOL);
        return self::write($ini, true);
    }

    /** @var int */
    public static function handleDelPid($deadPid)
    {
        $array = ProcessHelper::read();
        $ini = '';
        foreach ($array as $v) {
            list($pid, $ppid, $queueName, $qos) = $v;
            if ($pid == $deadPid) {
                // $this->logger->addLog(sprintf("[handle %s] %d", HandlerInterface::EVENT_DELETE_PID, $pid));
                continue;
            }
            $ini .= sprintf("%d_%d = %s,%d%s", $pid, $ppid, $queueName, $qos, PHP_EOL);
        }
        return self::write($ini);
    }

    /** @var int */
    public static function handleDelPpid($domain)
    {
        $array = ProcessHelper::read();
        foreach ($array as $v) {
            list($pid, $ppid, $queueName, $qos) = $v;
            // $this->logger->addLog(sprintf("[handle %s] %d", HandlerInterface::EVENT_DELETE_PPID, $ppid));
            posix_kill($pid, SignoHelper::KILL_CHILD_STOP);
        }
        return self::flush();
    }

    public static function killAll()
    {
        $array = self::read();
        foreach ($array as $v) {
            list($pid, $ppid, $queueName, $qos) = $v ;
            $res = posix_kill($pid, SignoHelper::KILL_CHILD_STOP);
            // $this->logger->addLog(sprintf("[flush] kill -%d %d ,result is %s", SignoHelper::KILL_CHILD_STOP, $pid, $res));
        }

        return self::flush();
    }
}
