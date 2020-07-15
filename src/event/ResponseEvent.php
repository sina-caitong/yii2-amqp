<?php

namespace pzr\amqp\event;

use yii\base\Event;

class ResponseEvent extends Event
{
    /**
     * @var string
     */
    public $uuid;

    /**
     * @var string|array|mix
     */
    public $response;

    public $exchangeName;

    public $routingKey;
}