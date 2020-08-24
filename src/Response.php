<?php

namespace pzr\amqp;

use yii\base\Object;

class Response extends Object
{

    public $response;

    /**
     * Get the value of response
     */ 
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Set the value of response
     *
     * @return  self
     */ 
    public function setResponse($response)
    {
        $this->response = $response;

        return $this;
    }
}