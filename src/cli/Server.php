<?php

namespace pzr\amqp\cli;

use pzr\amqp\cli\helper\AmqpIni;
use pzr\amqp\cli\logger\Logger;

class Server
{
    private $command = null;
    private $logger = null;
    private $unixPath;

    public function __construct()
    {
        $this->command = new Command();
        $this->unixPath = AmqpIni::getUnix();
        list($access_log, $error_log, $level) = AmqpIni::getDefaultLogger();
        $this->logger = new Logger($access_log, $error_log, $level);
    }


    public function run()
    {
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        @unlink($this->unixPath);
        socket_bind($socket, $this->unixPath) or exit('bind error');
        chmod($this->unixPath, 0777);
        socket_listen($socket);
        $client = [$socket];
        $write = $except = [];
        while(true) {
            $read = $client;
            if (socket_select($read, $write, $except, null) < 1) continue;
            if (in_array($socket, $read)) {
                $clientSocket = socket_accept($socket);
                $client[] = $clientSocket;
                $index = array_search($socket, $read);
                if ($index !== false) unset($read[$index]);
            }
            foreach ($read as $sock) {
                if ($sock === $socket) continue;
                $buffer = socket_read($sock, 1024);
                if (!empty($buffer)) {
                    $this->logger->addLog('request:' . $buffer);
                    $response = $this->command->dispatch($buffer);
                    $this->logger->addLog('response：' . $response);
                } else {
                    $index = array_search($sock, $client);
                    if ($index !== false) {
                        unset($client[$index]);
                    }
                    socket_close($sock);
                }
            }
        }
    }
}
