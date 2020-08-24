<?php
namespace pzr\amqp\cli\handler;

use pzr\amqp\AmqpJob;
use pzr\amqp\cli\helper\ProcessHelper;
use pzr\amqp\cli\logger\Logger;

// 对象中不能有Closer对象，否则报错：无法序列化
class ConsumerJob extends AmqpJob
{

    public $pid;
    public $ppid;
    public $queueName;
    public $qos;
    public $event;
    protected $handler;
    /** @var Logger  */
    protected $logger;

    public function init() {
        $access_log = dirname(__DIR__).'/log/access.log';
        $this->logger = new Logger($access_log, $error_log='');
    }

    public function execute() {
        $this->logger->addLog('event:'.$this->event);
        switch ($this->event) {
            case HandlerInterface::EVENT_ADD_QUEUE:
                ProcessHelper::handleAddQueue(
                    $this->pid,
                    $this->ppid,
                    $this->queueName,
                    $this->qos
                );
                break;
            case HandlerInterface::EVENT_DELETE_PID:
                ProcessHelper::handleDelPid($this->pid);
                break;
            case HandlerInterface::EVENT_DELETE_PPID:
                ProcessHelper::handleDelPpid($this->ppid);
                break;
            default:
                break;
        }
        return true;
    }

}