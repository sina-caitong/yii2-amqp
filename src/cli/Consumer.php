<?php

namespace pzr\amqp\cli;

use Monolog\Logger;
use pzr\amqp\cli\helper\AmqpIni;
use yii\base\BaseObject;

class Consumer extends BaseObject
{
    public $queueName;
    public $qos;
    public $numprocs;
    public $program;
    // public $command;
    public $duplicate;
    // public $directory;
    public $queue;
    public $script;
    public $request;

    public function getQueues()
    {
        $array = [];
        if (empty($this->program) || empty($this->queueName) || empty($this->script)) {
            AmqpIni::addLog(sprintf(
                "program:%s, queueName:%s script:%s",
                $this->program,
                $this->queueName,
                $this->script
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
