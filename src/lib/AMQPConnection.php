<?php

namespace pzr\amqp\lib;

use PhpAmqpLib\Connection\AMQPStreamConnection;

class AMQPConnection extends AMQPStreamConnection
{

    /**
     * Maximum time to wait for channel operations, in seconds
     * @var float $channel_rpc_timeout
     */
    private $channel_rpc_timeout;

    /**
     * Fetches a channel object identified by the numeric channel_id, or
     * create that object if it doesn't already exist.
     *
     * @param int $channel_id
     * @return AMQPChannel
     */
    public function channel($channel_id = null)
    {
        if (isset($this->channels[$channel_id])) {
            return $this->channels[$channel_id];
        }

        $channel_id = $channel_id ? $channel_id : $this->get_free_channel_id();
        $ch = new AMQPChannel($this, $channel_id, true, $this->channel_rpc_timeout);
        $this->channels[$channel_id] = $ch;

        return $ch;
    }
}