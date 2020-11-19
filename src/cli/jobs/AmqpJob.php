<?php

namespace pzr\amqp\jobs;

use pzr\amqp\JobInterface;
use yii\base\BaseObject;

class AmqpJob extends BaseObject implements JobInterface
{

    /**
     * RPC客户端请求的唯一标志
     *
     * @var string
     */
    private $uuid;

    /**
     * 优先队列定义
     *
     * @var integer
     */
    private $priority = 0;

    public function init() {
        $this->getUuid() or $this->setUuid(uniqid(true));
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