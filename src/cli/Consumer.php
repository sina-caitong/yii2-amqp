<?php

namespace pzr\amqp\cli;

use Monolog\Logger;
use pzr\amqp\cli\helper\AmqpIniHelper;
use yii\base\BaseObject;

class Consumer extends BaseObject
{
    /** 队列名称 */
    public $queueName;
    /** 消费者同时消费数量 */
    public $qos = 1;
    /** 开启多少个消费者 */
    public $numprocs = 1;
    /** 当前配置的唯一标志 */
    public $program;
    /** 执行的命令 */
    public $command;
    /** 开启消费者副本的数量 */
    public $duplicate = 1;
    /** 当前工作的目录 */
    public $directory;

    /** 通过 $qos $queueName $duplicate 生成的 $queue */
    public $queue;
    /** 程序执行日志记录 */
    public $logfile = '';

    public function getQueues()
    {
        $array = [];
        if (empty($this->program) || empty($this->queueName) || empty($this->command)) {
            AmqpIniHelper::addLog(sprintf(
                "program:%s, queueName:%s command:%s",
                $this->program,
                $this->queueName,
                $this->command
            ), Logger::ERROR);
            return $array;
        }
        $duplicate = $this->duplicate > 1 ? $this->duplicate : 1;
        $numprocs = $this->numprocs >= 1 ? $this->numprocs : 1;
        $queue = $this->queueName;
        while ($numprocs--) {
            for ($i = 0; $i < $duplicate; $i++) {
                $queueName = $this->duplicate > 1 ? $queue . '_' . $i : $queue;
                $this->queue = $queueName;
                $this->numprocs = 1;
                $this->duplicate = 1;
                $array[] = $this;
            }
        }
        return $array;
    }
}
