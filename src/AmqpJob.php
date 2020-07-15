<?php

namespace pzr\amqp;

use yii\base\BaseObject;

class AmqpJob extends BaseObject implements JobInterface
{

    /**
     * RpcAmqp will be used
     *
     * @var string
     */
    private $uuid;

    /**
     * 优先队列定义的时候用到
     *
     * @var integer
     */
    private $priority = 0;

    public function init() {
        $this->setUuid(uniqid(true));
        parent::init();
    }

    public function execute()
    {
        return true;
    }

    public function setPriority($priority)
    {
        $this->priority = $priority;
        return $this;
    }

    public function getPriority()
    {
        return $this->priority;
    }


    /**
     * Get the value of uuid
     */ 
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * Set the value of uuid
     *
     * @return  self
     */ 
    public function setUuid($uuid)
    {
        $this->uuid = $uuid;

        return $this;
    }
}