<?php

namespace pzr\amqp\cli\command;

use Monolog\Logger;
use pzr\amqp\cli\communication\CommunFactory;
use pzr\amqp\cli\helper\AmqpIniHelper;
use pzr\amqp\cli\helper\ProcessHelper;
use pzr\amqp\cli\helper\SignoHelper;
use pzr\amqp\cli\command\SwooleDispatcher;

class SwooleCommand extends BaseCommand implements CommandInterface
{
    protected $end = false;
    protected $childs = [];

    public function dispatch($input)
    {
        $input = trim(strtolower($input));
        list($command, $pid) = explode('|', $input);
        $this->logger->addLog('dispatch command: ' . $command, Logger::DEBUG);
        if (!in_array($command, $this->_commands)) {
            $notice = 'avaliable commands are :' . implode('|', $this->_commands);
            $this->logger->addLog($notice, Logger::NOTICE);
            return false;
        }
        if (in_array($command, [self::COPY, self::RESTART, self::STOP]) && empty($pid)) {
            $notice = sprintf("available is %s|{pid}", $command);
            $this->logger->addLog($notice, Logger::NOTICE);
            return false;
        }

        switch ($command) {
            case self::COPY:
                $this->copyOne($pid);
                break;
            case self::STOP:
                $this->stopOne($pid);
                break;
            case self::RESTART:
                $this->reloadOne($pid);
                break;
            case self::START_ALL:
                $this->startAll();
                break;
            case self::STOP_ALL:
                $this->stopAll();
                break;
            case self::RELOAD_ALL:
                $this->reloadAll();
                break;
            default:
                break;
        }
    }

    public function startAll()
    {
        (new SwooleDispatcher())->run();
    }
    
    public function stopAll()
    {
        $array = ProcessHelper::read();
        foreach ($array as $v) {
            list($pid, $ppid, $queueName, $program) = $v ;
            $res = \Swoole\Process::kill($pid, SignoHelper::KILL_CHILD_STOP);
            AmqpIniHelper::addLog(sprintf("[flush] kill -%d %d ,result is %s", SignoHelper::KILL_CHILD_STOP, $pid, $res));
        }
        ProcessHelper::flush();
        $this->commun->flush();
        return true;
    }

    public function reloadAll()
    {
        $array = ProcessHelper::read();
        $domain = AmqpIniHelper::readPpid();
        $isAlive = \Swoole\Process::kill($domain, 0);
        foreach ($array as $v) {
            list($pid, $ppid, $queueName, $program) = $v;
            $signo = $isAlive && $ppid == $domain ?  SignoHelper::KILL_CHILD_RELOAD : SignoHelper::KILL_CHILD_STOP;
            $res = \Swoole\Process::kill($pid, $signo);
            $this->logger->addLog(sprintf("[handleReload] kill -%d %d , result is %d", $signo, $pid, $res), BaseLogger::NOTICE);
        }
        return true;
    }

    public function stopOne($pid)
    {
        $this->logger->addLog(sprintf("[handleStop] kill -%d %d", SignoHelper::KILL_CHILD_STOP, $pid));
        return \Swoole\Process::kill($pid, SignoHelper::KILL_CHILD_STOP);
    }

    public function CopyOne($pid)
    {
        $ppid = AmqpIniHelper::readPpid();
        if (empty($ppid)) {
            $this->logger->addLog(
                sprintf("pid:%d, ppid:%d", $pid, $ppid), Logger::NOTICE
            );
            return false;
        }
        list($queueName, $program) = ProcessHelper::readPid($pid, $ppid);
        if (empty($queueName) || empty($program)) {
            $this->logger->addLog(
                sprintf("queueName:%s, program:%s", $queueName, $program), Logger::NOTICE
            );
            return false;
        }

        $this->commun->write($queueName, $program);
        $this->commun->close();
        return true;
    }

    public function reloadOne($pid)
    {
        $this->logger->addLog(sprintf("[handleRestart] kill -%d %d", SignoHelper::KILL_CHILD_RELOAD, $pid));
        return \Swoole\Process::kill($pid, SignoHelper::KILL_CHILD_RELOAD);
    }
}
