<?php


namespace pzr\amqp\cli;

use Exception;
use Monolog\Logger;
use pzr\amqp\cli\helper\AmqpIni;

class Client
{
    public function request($command, $username, $ip)
    {
        AmqpIni::addLog(
            sprintf("receive command: %s by %s IP %s", $command, $username, $ip)
        );
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if (!socket_connect($socket, AmqpIni::getUnix())) {
            AmqpIni::addLog('Client connect Serve failed');
            exit(2);
        }
        try {
            $ret = socket_write($socket, $command, strlen($command));
        } catch (Exception $e) {
            AmqpIni::addLog($e->getMessage(), Logger::ERROR);
        }

        socket_close($socket);
    }
}
