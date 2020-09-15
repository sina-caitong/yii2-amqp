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
        // $pm = new \Swoole\Process\ProcessManager();
        // $pm->add(function ($pool, $workerId) {
        //     (new SwooleDispatcher())->run();
        // });
        // $pm->start();
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
    }
    public function stopOne($pid)
    {
    }
    public function CopyOne($pid)
    {
    }
    public function reloadOne($pid)
    {
    }
}
