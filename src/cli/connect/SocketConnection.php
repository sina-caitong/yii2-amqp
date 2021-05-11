<?php


namespace pzr\amqp\cli\connect;

use pzr\amqp\cli\command\Command;
use pzr\amqp\cli\helper\AmqpIniHelper;

class SocketConnection implements ConnectionInterface
{

    private $socket;
    private $command;

    public function __construct($host)
    {
        if (!extension_loaded('sockets')) {
            throw new \Exception('Sockets extension not found');
        }

        $this->socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        if ($this->socket === false) {
            AmqpIniHelper::exit('socket create error');
        }

        socket_bind($this->socket, $host)
            or AmqpIniHelper::exit('socket bind error');
        socket_listen($this->socket);
        // AmqpIniHelper::addLog('amqp server started');

        $this->command = new Command();
    }

    public function receive($clientSock, &$client)
    {
        $buffer = socket_read($clientSock, 1024);
        if (!empty($buffer)) {
            $this->command->dispatch($buffer);
        } else {
            $index = array_search($clientSock, $client);
            if ($index !== false) {
                unset($client[$index]);
            }
            $this->close($clientSock);
        }
    }

    public function close($socket)
    {
        socket_close($socket);
    }

    public function start()
    {
        $client = [$this->socket];
        $write = $except = [];
        while (true) {
            $read = $client;
            if (socket_select($read, $write, $except, null) < 1) continue;
            if (in_array($this->socket, $read)) {
                $clientSocket = socket_accept($this->socket);
                $client[] = $clientSocket;
                $index = array_search($this->socket, $read);
                if ($index !== false) unset($read[$index]);
            }
            foreach ($read as $sock) {
                if ($sock === $this->socket) continue;
                $this->receive($sock, $client);
            }
        }

        register_shutdown_function(function() {
            AmqpIniHelper::addLog('amqp server closed');
            $this->close($this->socket);
        });
    }
}
