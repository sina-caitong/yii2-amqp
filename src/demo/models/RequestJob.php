<?php

namespace app\models;

use pzr\amqp\AmqpJob;

/**
 * @example DEMO
 */
class RequestJob extends AmqpJob
{    

    public $request;

    public function execute()
    {
        $response = $this->request . ', corrid:' . $this->getUuid();
        return $response;
    }
}