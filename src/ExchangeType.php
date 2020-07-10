<?php


namespace pzr\amqp;

class ExchangeType {

    const DIRECT = 'direct';
    const TOPIC = 'topic';
    const FANOUT = 'fanout';
    const HEADER = 'header';

    public static $exchangeTypes = [
        self::DIRECT,
        self::TOPIC,
        self::FANOUT,
        self::HEADER,
    ];

    public static function isDefined($type) {
        if (!in_array($type, self::$exchangeTypes)) {
            return false;
        }
        return true;
    }
}