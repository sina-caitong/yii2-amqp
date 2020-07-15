<?php

namespace app\models;

use pzr\amqp\AmqpJob;

class CountJob extends AmqpJob
{    

    public $count;
        
    public function execute()
    {
        return $this->count;
    }
}