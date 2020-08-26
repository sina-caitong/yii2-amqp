<?php


namespace pzr\amqp\cli\Communication;

interface CommunInterface
{
    public function open();
    public function close();
    public function read();
    public function write(string $queueName, string $program);
    public function write_batch(array $array);
    public function flush();
}