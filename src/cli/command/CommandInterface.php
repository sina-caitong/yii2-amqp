<?php


namespace pzr\amqp\cli\command;

interface CommandInterface
{
    public function dispatch(string $input);
}