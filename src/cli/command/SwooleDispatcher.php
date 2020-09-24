<?php

namespace pzr\amqp\cli\command;

use Exception;
use Monolog\Logger as BaseLogger;
use pzr\amqp\cli\communication\CommunFactory;
use pzr\amqp\cli\Consumer;
use pzr\amqp\cli\handler\HandlerFactory;
use pzr\amqp\cli\helper\AmqpIniHelper;
use pzr\amqp\cli\helper\ProcessHelper;
use pzr\amqp\cli\helper\SignoHelper;
use pzr\amqp\cli\logger\Logger;

// 读取配置文件并且分发任务
class SwooleDispatcher
{
    /** 待启动的消费者数组 */
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

    protected $atomic;

    protected $uniq_consumers;

    public function __construct()
    {
        $this->consumers = AmqpIniHelper::parseIni();
        $this->handler = HandlerFactory::getHandler();
        $this->commun = CommunFactory::getInstance();
        $this->logger = AmqpIniHelper::getLogger();
        $this->atomic = new \Swoole\Atomic();
    }

    public function run()
    {
        if (empty($this->consumers)) {
            $this->logger->addLog('consumer is empty, nothing to do', BaseLogger::NOTICE);
            return;
        }
        if ($this->_notifyMaster()) return;
        // 首先将自己变为守护进程
        \Swoole\Process::daemon($nochdir = true, $noclose = true);
        // swoole_set_process_name('AMQP Master');
        // 将主进程ID写入文件
        AmqpIniHelper::writePpid(getmypid());
        // \Swoole\Process->name('AMQP Master');
        // 然后启动子进程
        foreach ($this->consumers as $c) {
            $key = ProcessHelper::getConsumerKey($c->queueName, $c->program);
            if (!isset($this->uniq_consumers[$key])) {
                $this->uniq_consumers[$key] = $c;
            }
            $this->fork($c);
        }

        unset($this->consumers);
        // 清空管道里的数据
        $this->commun->flush();

        // master进程继续
        while (true) {
            // $i = $this->atomic->get();
            // while ($i--) {
            //     $status = \Swoole\Process::wait(false);
            //     if ($status) {
            //         $this->atomic->sub(1);
            //         $this->reload($status);
            //     }
            // }

            foreach($this->childPids as $k=>$pid) {
                $status = \Swoole\Process::wait(false);
                if ($status) {
                    unset($this->childPids[$k]);
                    $this->reload($status);
                }
            }
            if (empty($this->childPids)) break;
            $this->receiveNotify();
            sleep(1); 
        }
    }

    /**
     * reload 被杀死的子进程
     *
     * @return void
     */
    protected function reload($status)
    {
        $signal = $status['signal'];
        $pid = $status['pid'];
        $code = $status['code'];
        $pidinfo = $this->handler->delPid($pid, getmypid());
        if (empty($pidinfo)) return false;
        switch ($signal) {
            case SignoHelper::KILL_CHILD_STOP:
                break;
            case SignoHelper::KILL_CHILD_RELOAD:
                list($queueName, $program) = $pidinfo;
                $c = $this->getConsumer($queueName, $program);
                if (empty($c)) break;
                $this->fork($c);
                break;
        }
    }

    /**
     * 父进程接收到子进程的通信后启动一个新的子进程
     * 
     * @return void
     */
    protected function receiveNotify()
    {
        try {
            $array = $this->commun->read();
            if (empty($array) || !is_array($array)) return;
            foreach ($array as $v) {
                list($queueName, $program) = $v;
                if (empty($queueName) || empty($program)) continue;
                $c = $this->getConsumer($queueName, $program);
                if (empty($c)) continue;
                $this->fork($c);
            }
        } catch (Exception $e) {
            $this->logger->addLog($e->getMessage(), BaseLogger::ERROR);
        }
    }

    /** @return Consumer */
    protected function getConsumer($queueName, $program)
    {
        $key = ProcessHelper::getConsumerKey($queueName, $program);
        if (isset($this->uniq_consumers[$key])) {
            return $this->uniq_consumers[$key];
        }
        $config = AmqpIniHelper::getConsumersByProgram($program);
        $config['queueName'] = $queueName;
        $config['duplicate'] = 1;
        $config['numprocs'] = 1;
        $consumer = new Consumer($config);
        $this->uniq_consumers[$key] = $consumer;
        return $consumer;
    }


    /**
     * 父进程存活情况下，只会通知父进程信息，否则可能产生多个守护进程
     *
     * @return bool 父进程是否健在
     */
    private function _notifyMaster()
    {
        $ppid = AmqpIniHelper::readPpid();
        $isAlive = \Swoole\Process::kill($ppid, 0);
        if (!$isAlive) return false;
        $this->logger->addLog('ppid:' . $ppid . ' is alive', BaseLogger::NOTICE);
        $queues = array();
        foreach ($this->consumers as $c) {
            $queues[] = [
                'queueName' => $c->queueName,
                'program' => $c->program
            ];
        }
        $this->commun->write_batch($queues);
        $this->commun->close();
        return true;
    }

    /**
     * fork一个新的子进程
     *
     * @param string $queueName
     * @param integer $qos
     * @return void
     */
    protected function fork(Consumer $c)
    {
        // swoole 只能在cli模式下使用
        $process = new \Swoole\Process(function () use ($c) {
            $this->worker($c);
        });
        // $this->atomic->add(1);
        $this->childPids[] = $process->pid;
        $process->start();
    }

    protected function worker(Consumer $c)
    {
        $this->handler->addQueue(getmypid(), posix_getppid(), $c->queueName, $c->program);
        $args = [
            $c->script,
            $c->request,
            $c->queueName,
            $c->qos
        ];
        $flag = pcntl_exec(AmqpIniHelper::getCommand(), $args);
        if ($flag === false) {
            exit(1);
        }
        exit(0);
    }
}
