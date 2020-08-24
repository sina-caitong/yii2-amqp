<?php

namespace pzr\amqp\cli;

use pzr\amqp\cli\Communication\CommunFactory;
use pzr\amqp\cli\handler\HandlerFactory;
use pzr\amqp\cli\helper\AmqpIni;
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
    /** @var \pzr\amqp\cli\Communication\CommunInterface */
    private $commun = null;
    private $childs = array();
    private $end = false;

    public function __construct()
    {
        $this->handler = HandlerFactory::getHandler();
        $this->commun = CommunFactory::getInstance();
        list($access_log, $error_log, $level) = AmqpIni::getDefaultLogger();
        $this->logger = new Logger($access_log, $error_log, $level);
    }


    public function dispatch($input)
    {
        $input = trim(strtolower($input));
        list($command, $pid) = explode('|', $input);
        if (!in_array($command, $this->_commands)) {
            return 'avaliable commands are :' . implode('|', $this->_commands);
        }
        if (in_array($command, [self::COPY, self::RESTART, self::STOP]) && empty($pid)) {
            return sprintf("available is %s|{pid}", $command);
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
            exit();
        } elseif ($pid > 0) {
            $this->childs[] = $pid;
        } else {
            $path = __DIR__ . '/run/ExecDispatcher.php';
            $cmd = AmqpIni::getCommand();
            pcntl_exec($cmd, [$path]);
            // shell_exec($cmd); //生成的子进程ID无法捕捉，所以自己手动生成子进程处理
            exit(0);
        }
        while (!$this->end) {
            pcntl_signal_dispatch();
        }
    }

    protected function handleStopAll()
    {
        ProcessHelper::killAll();
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
        $domain = AmqpIni::readPpid();
        $isAlive = AmqpIni::checkProcessAlive($domain);
        foreach ($array as $v) {
            list($pid, $ppid, $queueName, $qos) = $v;
            $signo = $isAlive && $ppid == $domain ?  SignoHelper::KILL_CHILD_RELOAD : SignoHelper::KILL_CHILD_STOP;
            $res = posix_kill($pid, $signo);
            $this->logger->addLog(sprintf("[handler ReloadAll] kill -%d %d , result is %d", $signo, $pid, $res));
        }
        return true;
    }


    protected function handleCopy($pid)
    {
        $ppid = AmqpIni::readPpid();
        list($queueName, $qos) = ProcessHelper::readPid($pid, $ppid);
        if (empty($queueName) || empty($qos)) {
            return false;
        }

        $this->commun->write($queueName, $qos);
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
