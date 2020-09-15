<?php


namespace pzr\amqp\cli\connect;

use pzr\amqp\cli\command\Command;
use pzr\amqp\cli\command\SwooleCommand;
use pzr\amqp\cli\helper\AmqpIniHelper;

class SwooleConnection implements ConnectionInterface
{

    private $server;
    private $command;

    public function __construct($host)
    {
        $this->server = new \Swoole\Server($host, $port = 0, $mode = SWOOLE_PROCESS, $sock_type = SWOOLE_UNIX_STREAM);
        $this->command = new SwooleCommand();
    }

    public function start()
    {
        $this->server->on('connect', function ($server, $fd) {
            //打开连接
            // AmqpIniHelper::addLog('swoole client connect');
        });

        //监听数据接收事件
        $this->server->on('Receive', function ($server, $fd, $from_id, $data) {
            $this->command->dispatch($data);
        });

        //监听连接关闭事件
        $this->server->on('Close', function ($server, $fd) {
            // AmqpIniHelper::addLog('swoole client close');
        });

        //启动服务器
        $this->server->start();
    }

    
}
