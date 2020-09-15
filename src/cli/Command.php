<?php

namespace pzr\amqp\cli;

use Exception;
use Monolog\Logger as BaseLogger;
use pzr\amqp\cli\communication\CommunFactory;
use pzr\amqp\cli\handler\HandlerFactory;
use pzr\amqp\cli\helper\AmqpIniHelper;
use pzr\amqp\cli\helper\ProcessHelper;
use pzr\amqp\cli\helper\SignoHelper;
use pzr\amqp\cli\logger\Logger;

class Command
{
    const STOP = 'stop';
    const RESTART = 'restart';
    const START_ALL = 'startall';
    const STOP_ALL = 'stopall';
    const RELOAD_ALL = 'reloadall';
    const COPY = 'copy';

    private $_commands = [
        self::STOP,
        self::RESTART,
        self::START_ALL,
        self::STOP_ALL,
        self::RELOAD_ALL,
        self::COPY,
    ];

    /** @var \pzr\amqp\cli\handler\BaseHandler */
    private $handler = null;
    /** @var Logger */
    private $logger = null;
    /** @var \pzr\amqp\cli\communication\CommunInterface */
    private $commun = null;
    private $childs = array();
    private $end = false;

    public function __construct()
    {
        $this->handler = HandlerFactory::getHandler();
        $this->commun = CommunFactory::getInstance();
        $this->logger = AmqpIniHelper::getLogger();
    }


    public function dispatch($input)
    {
        $input = trim(strtolower($input));
        list($command, $pid) = explode('|', $input);
        $this->logger->addLog('dispatch command: ' . $command, BaseLogger::DEBUG);
        if (!in_array($command, $this->_commands)) {
            $notice = 'avaliable commands are :' . implode('|', $this->_commands);
            $this->logger->addLog($notice, BaseLogger::NOTICE);
            return false;
        }
        if (in_array($command, [self::COPY, self::RESTART, self::STOP]) && empty($pid)) {
            $notice = sprintf("available is %s|{pid}", $command);
            $this->logger->addLog($notice, BaseLogger::NOTICE);
            return false;
        }
        switch ($command) {
            case self::COPY:
                return $this->handleCopy($pid);
            case self::STOP:
                return $this->handleStop($pid);
            case self::RESTART:
                return $this->handleRestart($pid);
            case self::START_ALL:
                return $this->handleStartAll();
            case self::STOP_ALL:
                return $this->handleStopAll();
            case self::RELOAD_ALL:
                return $this->handleReloadAll();
            default:
                break;
        }
    }

    protected function handleStartAll()
    {
        $this->end = false;
        pcntl_signal(SIGCHLD, function ($signo, $siginfo) {
            foreach ($this->childs as $k => $pid) {
                $result = pcntl_waitpid($pid, $status, WNOHANG);
                if ($result == $pid || $result == -1) {
                    unset($this->childs[$k]);
                }
            }
            if (empty($this->childs)) $this->end = true;
        });
        $pid = pcntl_fork();
        if ($pid < 0) {
            exit(0);
        } elseif ($pid > 0) {
            $this->childs[] = $pid;
        } else {
            $path = __DIR__ . '/run/ExecDispatcher.php';
            pcntl_exec(AmqpIniHelper::getCommand(), [$path])
                or $this->logger->addLog('shell exec error' ,BaseLogger::ERROR);
            // shell_exec($cmd); //生成的子进程ID无法捕捉
            exit(0);
        }
        while (!$this->end) {
            pcntl_signal_dispatch();
        }
    }

    protected function handleStopAll()
    {
        ProcessHelper::killAll();
        $handler = CommunFactory::getInstance();
        $handler->flush();
        return true;
    }

    /**
     * 杀死所有的子进程，然后父进程接收到信号之后会重启所有的子进程
     *
     * @return bool
     */
    protected function handleReloadAll()
    {
        $array = ProcessHelper::read();
        $domain = AmqpIniHelper::readPpid();
        $isAlive = AmqpIniHelper::checkProcessAlive($domain);
        foreach ($array as $v) {
            list($pid, $ppid, $queueName, $program) = $v;
            $signo = $isAlive && $ppid == $domain ?  SignoHelper::KILL_CHILD_RELOAD : SignoHelper::KILL_CHILD_STOP;
            $res = posix_kill($pid, $signo);
            $this->logger->addLog(sprintf("[handleReload] kill -%d %d , result is %d", $signo, $pid, $res), BaseLogger::NOTICE);
        }
        return true;
    }


    protected function handleCopy($pid)
    {
        $ppid = AmqpIniHelper::readPpid();
        if (empty($ppid)) {
            $this->logger->addLog(
                sprintf("pid:%d, ppid:%d", $pid, $ppid), BaseLogger::NOTICE
            );
            return false;
        }
        list($queueName, $program) = ProcessHelper::readPid($pid, $ppid);
        if (empty($queueName) || empty($program)) {
            $this->logger->addLog(
                sprintf("queueName:%s, program:%s", $queueName, $program), BaseLogger::NOTICE
            );
            return false;
        }

        $this->commun->write($queueName, $program);
        $this->commun->close();
        return true;
    }

    protected function handleRestart($pid)
    {
        $this->logger->addLog(sprintf("[handleRestart] kill -%d %d", SignoHelper::KILL_CHILD_RELOAD, $pid));
        return posix_kill($pid, SignoHelper::KILL_CHILD_RELOAD);
    }

    protected function handleStop($pid)
    {
        $this->logger->addLog(sprintf("[handleStop] kill -%d %d", SignoHelper::KILL_CHILD_STOP, $pid));
        return posix_kill($pid, SignoHelper::KILL_CHILD_STOP);
    }
}
