<?php
namespace pzr\amqp\cli\Communication;

use pzr\amqp\AmqpJob;
use pzr\amqp\cli\helper\AmqpIni;
use pzr\amqp\cli\helper\ProcessHelper;
use pzr\amqp\cli\logger\Logger;

// 对象中不能有Closer对象，否则报错：无法序列化
class CommunJob extends AmqpJob
{
    public $queueName;
    public $program;
    protected $handler;
    /** @var Logger  */
    protected $logger;

    public function init() {
        list($access_log, $error_log, $level) = AmqpIni::getDefaultLogger();
        $this->logger = new Logger($access_log, $error_log, $level);
    }

    public function execute() {
        return [
            $this->queueName,
            $this->program
        ];
    }

}