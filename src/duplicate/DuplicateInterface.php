<?php

namespace pzr\amqp\duplicate;


interface DuplicateInterface
{

    public function getRoutingKey($routingKey, $duplicate=0);

}