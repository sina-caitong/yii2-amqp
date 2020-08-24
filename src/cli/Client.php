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
            sprintf("Client receive command: %s by %s IP %s", $command, $username, $ip)
        );
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if (!socket_connect($socket, AmqpIni::getUnix())) {
            AmqpIni::addLog('AMQP Client connect Serve failed');
            exit;
        }
        try {
            AmqpIni::addLog('ready send message：' . $command);
            $ret = socket_write($socket, $command, strlen($command));
            AmqpIni::addLog('send message result：' . $ret);
        } catch (Exception $e) {
            AmqpIni::addLog($e->getMessage(), Logger::ERROR);
        }

        socket_close($socket);
    }
}
