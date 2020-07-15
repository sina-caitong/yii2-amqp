<?php

namespace app\models;

use pzr\amqp\AmqpJob;

class RequestJob extends AmqpJob
{    

    public $request;

    public function execute()
    {
        $response = $this->request . ', corrid:' . $this->getUuid();
        return $response;
    }

    /**
     * Get the value of request
     */ 
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Set the value of request
     *
     * @return  self
     */ 
    public function setRequest($request)
    {
        $this->request = $request;

        return $this;
    }
}