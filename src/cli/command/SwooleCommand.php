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
        // 报错：无法再协程中使用 Fatal error: Uncaught Swoole\Error: must be forked outside the coroutine
        // swoole_async_set([
        //     'enable_coroutine' => false
        // ]);

        // \Swoole\Coroutine::set(['enable_coroutine' => false]);

        // ini_set('swoole.enable_coroutine','Off');
        // $process = new \Swoole\Process(function() {
        //     // (new SwooleDispatcher())->run();
        // });
        // $process->start();
        // \Swoole\Process::wait(false);

        // 报错：无法使用Server多进程
        // $pm = new \Swoole\Process\ProcessManager();
        // $pm->add(function ($pool, $workerId) {
        //     (new SwooleDispatcher())->run();
        // });
        // $pm->start();

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
            //     or $this->logger->addLog('shell exec error' ,Logger::ERROR);
            // shell_exec($cmd); //生成的子进程ID无法捕捉
            (new SwooleDispatcher())->run();
            exit(0);
        }
        while (!$this->end) {
            pcntl_signal_dispatch();
        }
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
