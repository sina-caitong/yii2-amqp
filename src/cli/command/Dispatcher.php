<?php

namespace pzr\amqp\cli\command;

use Exception;
use Monolog\Logger as BaseLogger;
use pzr\amqp\Amqp;
use pzr\amqp\cli\communication\CommunFactory;
use pzr\amqp\cli\Consumer;
use pzr\amqp\cli\handler\HandlerFactory;
use pzr\amqp\cli\helper\AmqpIniHelper;
use pzr\amqp\cli\helper\ProcessHelper;
use pzr\amqp\cli\helper\SignoHelper;
use pzr\amqp\cli\logger\Logger;

// 读取配置文件并且分发任务
class Dispatcher
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

    protected $uniq_consumers;

    public function __construct()
    {
        $this->consumers = AmqpIniHelper::parseIni();
        $this->handler = HandlerFactory::getHandler();
        $this->commun = CommunFactory::getInstance();
        $this->logger = AmqpIniHelper::getLogger();
    }

    public function run()
    {
        if (empty($this->consumers)) {
            $this->logger->addLog('consumer is empty, nothing to do', BaseLogger::NOTICE);
            return;
        }
        if ($this->_notifyMaster()) return;

        $pid = pcntl_fork();
        if ($pid < 0) {
            exit(2);
        } elseif ($pid > 0) {
            exit(0);
        }
        if (!posix_setsid()) {
            exit(2);
        }
        @cli_set_process_title('AMQP Master Process');
        // 将主进程ID写入文件
        AmqpIniHelper::writePpid(getmypid());
        // 父进程死亡回收所有的子进程
        // $this->installSignoHandler();

        foreach ($this->consumers as $c) {
            $key = ProcessHelper::getConsumerKey($c->queueName, $c->program);
            $this->uniq_consumers[$key] = $c;
            $this->fork($c);
        }
        unset($this->consumers);

        $this->commun->flush();
        // master进程继续
        while (true) {
            pcntl_signal_dispatch();
            $this->reload();
            $this->receiveNotify();
            if (empty($this->childPids)) break;
            sleep(1);
        }
    }

    /**
     * reload 被杀死的子进程
     *
     * @return void
     */
    protected function reload()
    {
        foreach ($this->childPids as $k => $pid) {
            $result = pcntl_waitpid($pid, $status, WNOHANG);
            if ($result == $pid || $result == -1) {
                unset($this->childPids[$k]);
                $signo = pcntl_wtermsig($status);
                $exitStatus = pcntl_wifexited($status);
                $this->logger->addLog("signo:{$signo}, exit:{$exitStatus}, pid:{$pid}", BaseLogger::NOTICE);
                $pidinfo = $this->handler->delPid($pid, getmypid());
                if (empty($pidinfo) || $signo == SignoHelper::KILL_CHILD_STOP || !empty($exitStatus)) continue;
                list($queueName, $program) = $pidinfo;
                $c = $this->getConsumer($queueName, $program);
                if (empty($c)) continue;
                $this->fork($c);
            }
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
        if (empty($config)) return null;
        $consumer = new Consumer($config);
        $this->uniq_consumers[$key] = $consumer;
        return $consumer;
    }

    public function installSignoHandler()
    {
        // 父进程死亡
        pcntl_signal(SignoHelper::KILL_DOMAIN_STOP, function () {
            $this->handler->delPid(0, getmypid());
        });
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
        $pid = pcntl_fork();
        if ($pid < 0) {
            $this->logger->addLog('fork error', BaseLogger::ERROR);
            exit(1);
        } elseif ($pid > 0) {
            $this->childPids[] = $pid;
        } else {
            $this->worker($c);
        }
    }

    protected function worker(Consumer $c)
    {
        $this->handler->addQueue(getmypid(), posix_getppid(), $c->queue, $c->program);
        @cli_set_process_title('AMQP worker consmer');
        $command = str_replace(['{php}', '{directory}', '{queueName}', '{qos}'], [
            AmqpIniHelper::getCommand(),
            $c->directory,
            $c->queue,
            $c->qos
        ], $c->command);
        preg_match_all('/(\S+)/', $command, $matches);
        $args = $matches[0];
        AmqpIniHelper::addLog($command);
        $sh = array_shift($args);
        $flag = pcntl_exec($sh, $args);
        if ($flag === false) {
            exit(1);
        }
        exit(0);

        // $command = str_replace(['{php}', '{queueName}', '{qos}'] ,[
        //     AmqpIniHelper::getCommand(),
        //     $c->queueName,
        //     $c->qos
        // ], $c->command);
        // $descriptorspec = array(
        //     0 => array("pipe", "r"),  // 标准输入，子进程从此管道中读取数据
        //     1 => array("pipe", "w"),  // 标准输出，子进程向此管道中写入数据
        //     2 => array("file", $c->logfile, "a") // 标准错误，写入到一个文件
        //  );
        // $process = proc_open($command, $descriptorspec, $pipe, $c->directory);
        // AmqpIniHelper::addLog(sprintf(
        //     "cd %s && %s, result:%s",
        //     $c->directory,
        //     $command,
        //     intval($process)
        // ));
        // // return $process;
        // if ($process === false) {
        //     exit(1);
        // }
        // exit(0);
    }
}
