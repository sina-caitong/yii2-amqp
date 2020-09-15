<?php

namespace pzr\amqp\cli\command;

use pzr\amqp\cli\communication\CommunFactory;
use pzr\amqp\cli\handler\HandlerFactory;
use pzr\amqp\cli\helper\AmqpIniHelper;

abstract class BaseCommand
{
    const STOP = 'stop';
    const RESTART = 'restart';
    const START_ALL = 'startall';
    const STOP_ALL = 'stopall';
    const RELOAD_ALL = 'reloadall';
    const COPY = 'copy';

    protected $_commands = [
        self::STOP,
        self::RESTART,
        self::START_ALL,
        self::STOP_ALL,
        self::RELOAD_ALL,
        self::COPY,
    ];

    /** @var \pzr\amqp\cli\handler\BaseHandler */
    protected $handler = null;
    /** @var Logger */
    protected $logger = null;
    /** @var \pzr\amqp\cli\communication\CommunInterface */
    protected $commun = null;

    public function __construct()
    {
        $this->handler = HandlerFactory::getHandler();
        $this->commun = CommunFactory::getInstance();
        $this->logger = AmqpIniHelper::getLogger();
    }

    protected abstract function startAll();
    protected abstract function stopAll();
    protected abstract function reloadAll();
    protected abstract function stopOne(int $pid);
    protected abstract function CopyOne(int $pid);
    protected abstract function reloadOne(int $pid);
}