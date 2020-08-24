<?php

namespace pzr\amqp\duplicate;

/**
 * 轮询路由方式
 */
class DuplicatePoll implements DuplicateInterface
{
    private static $duplicateIndex = 0;

    public function getRoutingKey($routingKey, $duplicate = 0)
    {
        $duplicate = intval($duplicate);
        if ($duplicate < 1) {
            return $routingKey;
        }
        $index = (self::$duplicateIndex++)%$duplicate;
        return $routingKey . '_' . $index;
    }
}
