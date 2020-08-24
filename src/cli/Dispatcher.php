<?php

namespace pzr\amqp\cli;

use Exception;
use Monolog\Logger as BaseLogger;
use pzr\amqp\Amqp;
use pzr\amqp\cli\Communication\CommunFactory;
use pzr\amqp\cli\handler\HandlerFactory;
use pzr\amqp\cli\helper\AmqpIni;
use pzr\amqp\cli\helper\SignoHelper;
use pzr\amqp\cli\logger\Logger;

// 读取配置文件并且分发任务
class Dispatcher
{
    /** 待启动的消费者数组 */
    protected $queues = array();
    /** 待启动的消费者配置文件数组 */
    protected $files = array();
    /** 子进程管理 */
    protected $childPids = array();
    // protected $end = false;
    /** @var \pzr\amqp\cli\handler\HandlerInterface 进程文件管理 */
    protected $handler = null;
    /** @var \pzr\amqp\cli\Communication\CommunInterface 进程通信 */
    protected $commun;
    /** @var Logger */
    protected $logger;
    /** @var \pzr\amqp\Amqp $amqp */
    protected $amqp;

    public function __construct()
    {
        $array = AmqpIni::parseIni();
        $this->files = $array['files'];
        $this->amqp = new Amqp($array['amqp']);
        $this->queues = $array['queues'];
        $this->handler = HandlerFactory::getHandler();
        $this->commun = CommunFactory::getInstance();
        list($access_log, $error_log, $level) = AmqpIni::getDefaultLogger();
        $this->logger = new Logger($access_log, $error_log, $level);
    }

    //{{
    public function byQueues()
    {
        if (empty($this->queues)) return;
        if ($this->_notifyMaster()) return;

        $pid = pcntl_fork();
        if ($pid < 0) {
            exit(-1);
        } elseif ($pid > 0) {
            exit(0);
        }
        if (!posix_setsid()) {
            exit(-1);
        }
        @cli_set_process_title('AMQP Master Process');
        // 将主进程ID写入文件
        AmqpIni::writePpid(getmypid());
        // 父进程被杀死，要不要杀死所有的子进程呢？为了业务的安全，不杀死！
        // $this->installSignoHandler();
        foreach ($this->queues as $v) {
            list($queueName, $qos) = $v;
            $this->fork($queueName, $qos);
        }

        // master进程继续
        while (true) {
            pcntl_signal_dispatch();
            $this->reload();
            $this->copy();
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
                $pidinfo = $this->handler->delPid($pid, getmypid());
                if (empty($pidinfo) || $signo == SignoHelper::KILL_CHILD_STOP) continue;
                list($queueName, $qos) = $pidinfo;
                $this->fork($queueName, $qos);
            }
        }
    }

    /**
     * 父进程接收到子进程的通信后COPY一个新的子进程
     * ？多请求情况下是否会出现写文件冲突 ？（目前并没发现）
     * 
     * @return void
     */
    protected function copy()
    {
        try {
            $array = $this->commun->read();
            if (empty($array) || !is_array($array)) return;
            foreach ($array as $v) {
                list($queueName, $qos) = $v;
                if (empty($queueName) || empty($qos)) continue;
                $this->fork($queueName, $qos);
            }
        } catch (Exception $e) {
            $this->logger->addLog($e->getMessage(), BaseLogger::ERROR);
        }
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
        $ppid = AmqpIni::readPpid();
        $isAlive = AmqpIni::checkProcessAlive($ppid);
        if (!$isAlive) return false;
        $this->commun->write_batch($this->queues);
        $this->commun->close();
        @posix_kill($ppid, SignoHelper::KILL_NOTIFY_PARENT);
        return true;
    }

    /**
     * fork一个新的子进程
     *
     * @param string $queueName
     * @param integer $qos
     * @return void
     */
    protected function fork(string $queueName, int $qos)
    {
        $pid = pcntl_fork();
        if ($pid < 0) {
            $this->logger->addLog('fork error', BaseLogger::ERROR);
            exit(-1);
        } elseif ($pid > 0) {
            $this->childPids[] = $pid;
        } else {
            @cli_set_process_title('AMQP worker consmer');
            try {
                $this->worker(getmypid(), posix_getppid(), $queueName, $qos);
            } catch (Exception $e) {
                $this->logger->addLog($e->getMessage(), BaseLogger::ERROR);
                exit(-1);
            }
            exit(0);
        }
    }

    protected function worker(int $pid, int $ppid, string $queueName, int $qos)
    {
        $this->handler->addQueue($pid, $ppid, $queueName, $qos);
        $consumerTag = md5($queueName . ',' . $qos);
        $this->amqp->consume($queueName, $qos, $consumerTag);
    }
    //}}

    //{{ 
    /**
     * 按消费者配置文件启动
     * @deprecated version
     * @return void
     */
    public function byFile()
    {
        $pid = pcntl_fork();
        if ($pid < 0) {
            exit(-1);
        } elseif ($pid > 0) {
            exit(0);
        }
        if (!posix_setsid()) {
            exit('setsid error');
        }

        foreach ($this->files as $file) {
            $pid = pcntl_fork();
            if ($pid < 0) {
                exit;
            } elseif ($pid > 0) {
                $this->childPids[] = $pid;
            } else {
                
            }
        }
        // master进程继续
        while (!$this->end) {
            sleep(1);
            pcntl_signal_dispatch();
        }
    }
    //}}
}
