<?php


namespace pzr\amqp\cli;

use Exception;
use Monolog\Logger;
use pzr\amqp\cli\helper\AmqpIniHelper;

class Client
{
    public function request($command, $username, $ip)
    {
        AmqpIniHelper::addLog(
            sprintf("client receive: %s by %s IP %s", $command, $username, $ip)
        );
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if (!socket_connect($socket, AmqpIniHelper::getUnix())) {
            AmqpIniHelper::exit('Client connect Serve failed');
        }
        try {
            $ret = socket_write($socket, $command, strlen($command));
        } catch (Exception $e) {
            AmqpIniHelper::addLog($e->getMessage(), Logger::ERROR);
        }

        socket_close($socket);
    }
}
