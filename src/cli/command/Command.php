<?php

namespace pzr\amqp\cli\command;

use Exception;
use Monolog\Logger as BaseLogger;
use pzr\amqp\cli\communication\CommunFactory;
use pzr\amqp\cli\helper\AmqpIniHelper;
use pzr\amqp\cli\helper\ProcessHelper;
use pzr\amqp\cli\helper\SignoHelper;
use pzr\amqp\cli\logger\Logger;

class Command extends BaseCommand implements CommandInterface
{
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
                return $this->copyOne($pid);
            case self::STOP:
                return $this->stopOne($pid);
            case self::RESTART:
                return $this->reloadOne($pid);
            case self::START_ALL:
                return $this->startAll();
            case self::STOP_ALL:
                return $this->stopAll();
            case self::RELOAD_ALL:
                return $this->reloadAll();
            default:
                break;
        }
    }

    public function startAll()
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
            // $path = __DIR__ . '/run/ExecDispatcher.php';
            // pcntl_exec(AmqpIniHelper::getCommand(), [$path])
            //     or $this->logger->addLog('shell exec error' ,BaseLogger::ERROR);
            // shell_exec($cmd); //生成的子进程ID无法捕捉
            try {
                (new Dispatcher())->run();
            } catch(Exception $e) {
                AmqpIniHelper::exit($e->__toString());
            }
            
            exit(0);
        }
        while (!$this->end) {
            pcntl_signal_dispatch();
        }
    }

    protected function stopAll()
    {
        ProcessHelper::killAll();
        $this->commun->flush();
        return true;
    }

    /**
     * 杀死所有的子进程，然后父进程接收到信号之后会重启所有的子进程
     *
     * @return bool
     */
    protected function reloadAll()
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


    protected function copyOne($pid)
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

    protected function reloadOne($pid)
    {
        $this->logger->addLog(sprintf("[handleRestart] kill -%d %d", SignoHelper::KILL_CHILD_RELOAD, $pid));
        return posix_kill($pid, SignoHelper::KILL_CHILD_RELOAD);
    }

    protected function stopOne($pid)
    {
        $this->logger->addLog(sprintf("[handleStop] kill -%d %d", SignoHelper::KILL_CHILD_STOP, $pid));
        return posix_kill($pid, SignoHelper::KILL_CHILD_STOP);
    }
}
