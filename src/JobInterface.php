<?php

namespace pzr\amqp;

interface JobInterface
{

    public function execute();

    public function setPriority($priority);

    public function getPriority();

}