<?php

namespace pzr\amqp\cli\command;

use Exception;
use Monolog\Logger as BaseLogger;
use pzr\amqp\Amqp;
use pzr\amqp\cli\communication\CommunFactory;
use pzr\amqp\cli\connect\StreamConnection;
use pzr\amqp\cli\Consumer;
use pzr\amqp\cli\handler\HandlerFactory;
use pzr\amqp\cli\helper\AmqpIniHelper;
use pzr\amqp\cli\helper\ProcessHelper;
use pzr\amqp\cli\helper\SignoHelper;
use pzr\amqp\cli\Http;
use pzr\amqp\cli\logger\Logger;

// 读取配置文件并且分发任务
class Dispatcher
{
    /** 
     * 待启动的消费者数组
     * @var array(Consumer)
     */
    protected $consumers = array();
    /** 待启动的消费者配置文件数组 */
    protected $files = array();
    /** 子进程管理 */
    protected $childPids = array();
    // protected $end = false;
    /** @var \pzr\amqp\cli\handler\HandlerInterface 进程文件管理 */
    protected $handler = null;
    /** @var \pzr\amqp\cli\communication\CommunInterface 进程通信 */
    protected $commun;
    /** @var Logger */
    protected $logger;

    protected $isRunning = [];

    protected $serializerConsumer;

    public function __construct()
    {
        $this->consumers = AmqpIniHelper::parseIni();
        $this->handler = HandlerFactory::getHandler();
        // $this->commun = CommunFactory::getInstance();
        $this->logger = AmqpIniHelper::getLogger();
    }

    public function run()
    {
        if (empty($this->consumers)) {
            $this->logger->addLog('consumer is empty, nothing to do', BaseLogger::NOTICE);
            return;
        }
        if ($this->_notifyMaster()) {
            AmqpIniHelper::addLog('master alive');
            return;
        }

        $pid = pcntl_fork();
        if ($pid < 0) {
            exit(2);
        } elseif ($pid > 0) {
            exit(0);
        }
        if (!posix_setsid()) {
            exit(2);
        }

        $stream = new StreamConnection('tcp://0.0.0.0:7865');
        @cli_set_process_title('AMQP Master Process');
        // 将主进程ID写入文件
        AmqpIniHelper::writePpid(getmypid());
        AmqpIniHelper::addLog(sprintf("共有%d个消费者待启动", count($this->consumers)));
        // master进程继续
        while (true) {
            $this->init();
            pcntl_signal_dispatch();
            $this->waitpid();
            if (empty($this->childPids)) {
                $stream->close($stream->getSocket());
                break;
            }
            $stream->accept(function ($uniqid, $action) {
                $this->handle($uniqid, $action);
                return $this->display();
            });
        }
    }

    protected function init()
    {
        foreach ($this->consumers as &$c) {
            switch ($c->state) {
                case Consumer::RUNNING:
                case Consumer::STOP:
                    break;
                case Consumer::NOMINAL:
                case Consumer::STARTING:
                    $this->fork($c);
                    break;
                case Consumer::STOPING:
                    if ($c->pid && posix_kill($c->pid, SIGTERM)) {
                        $this->reset($c, Consumer::STOP);
                    }
                    break;
                case Consumer::RESTART:
                    if (empty($c->pid)) {
                        $this->fork($c);
                        break;
                    }
                    if (posix_kill($c->pid, SIGTERM)) {
                        $this->reset($c, Consumer::STOP);
                        $this->fork($c);
                    }
                    break;
                default:
                    break;
            }
        }
    }

    protected function reset(Consumer $c, $state)
    {
        $c->pid = '';
        $c->uptime = '';
        $c->state = $state;
        $c->process = null;
    }



    /**
     * reload 被杀死的子进程
     *
     * @return void
     */
    protected function waitpid()
    {
        foreach ($this->childPids as $uniqid => $pid) {
            $result = pcntl_waitpid($pid, $status, WNOHANG);
            if ($result == $pid || $result == -1) {
                unset($this->childPids[$uniqid]);
                $c = &$this->consumers[$uniqid];
                $this->handler->delPid($pid, getmypid());
                $state = pcntl_wifexited($status) ? Consumer::EXITED : Consumer::STOP;
                $this->reset($c, $state);
            }
        }
    }


    /**
     * 父进程存活情况下，只会通知父进程信息，否则可能产生多个守护进程
     *
     * @return bool 父进程是否健在
     */
    private function _notifyMaster()
    {
        $ppid = AmqpIniHelper::readPpid();
        $isAlive = AmqpIniHelper::checkProcessAlive($ppid);
        if (!$isAlive) return false;
        $this->logger->addLog('ppid:' . $ppid . ' is alive', BaseLogger::NOTICE);
        // $queues = array();
        // foreach ($this->consumers as $c) {
        //     $queues[] = [
        //         'queueName' => $c->queueName,
        //         'program' => $c->program
        //     ];
        // }
        // $this->commun->write_batch($queues);
        // $this->commun->close();
        return true;
    }

    /**
     * fork一个新的子进程
     *
     * @param string $queueName
     * @param integer $qos
     * @return Consumer
     */
    protected function fork(Consumer $c)
    {
        $descriptorspec = [2 => ['file', $c->logfile, 'a'],];
        $process = proc_open('exec ' . $c->command, $descriptorspec, $pipes, $c->directory);
        if ($process) {
            $ret = proc_get_status($process);
            if ($ret['running']) {
                $c->state = Consumer::RUNNING;
                $c->pid = $ret['pid'];
                $c->process = $process;
                $c->uptime = date('m-d H:i');
                $this->childPids[$c->uniqid] = $ret['pid'];
                $this->handler->addQueue($ret['pid'], getmypid(), $c->queue, $c->program);
            } else {
                $c->state = Consumer::EXITED;
                proc_close($process);
            }
        } else {
            $c->state = Consumer::ERROR;
        }
        return $c;
    }

    public function display()
    {
        $location = 'http://127.0.0.1:7865';
        $basePath = dirname(__DIR__) . '/views';
        $scriptName = isset($_SERVER['SCRIPT_NAME']) &&
            !empty($_SERVER['SCRIPT_NAME']) &&
            $_SERVER['SCRIPT_NAME'] != '/' ? $_SERVER['SCRIPT_NAME'] : '/index.php';
        if ($scriptName == '/index.html') {
            AmqpIniHelper::addLog('route: 301');
            return Http::status_301($location);
        }

        $sourcePath = $basePath . $scriptName;
        if (!is_file($sourcePath)) {
            AmqpIniHelper::addLog('route: 404, sourcePath:' . $sourcePath);
            return Http::status_404();
        }

        ob_start();
        include $sourcePath;
        $response = ob_get_contents();
        ob_clean();

        AmqpIniHelper::addLog('route: 200');
        return Http::status_200($response);
    }




    public function handle($uniqid, $action)
    {
        if (!empty($uniqid) && !isset($this->consumers[$uniqid])) {
            return;
        }
        AmqpIniHelper::addLog('handle:' . $action);
        switch ($action) {
            case 'refresh':
                break;
            case 'restartall':
                $this->killall(true);
                break;
            case 'stopall':
                $this->killall();
                break;
            case 'stop':
                $c = &$this->consumers[$uniqid];
                if ($c->state != Consumer::RUNNING) break;
                $c->state = Consumer::STOPING;
                break;
            case 'start':
                $c = &$this->consumers[$uniqid];
                if ($c->state == Consumer::RUNNING) break;
                $c->state = Consumer::STARTING;
                break;
            case 'restart':
                $c = &$this->consumers[$uniqid];
                $c->state = Consumer::RESTART;
                break;
            case 'copy':
                $c = $this->consumers[$uniqid];
                $newC = clone $c;
                $newC->uniqid = uniqid('C');
                $newC->state = Consumer::NOMINAL;
                $newC->pid = '';
                $this->consumers[$newC->uniqid] = $newC;
                break;
            default:
                break;
        }
    }

    protected function killall($restart = false)
    {
        foreach ($this->consumers as &$c) {
            $c->state = $restart ? Consumer::RESTART : Consumer::STOPING;
        }
    }
}
