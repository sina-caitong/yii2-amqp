<?php

namespace pzr\amqp\lib;

use PhpAmqpLib\Channel\AMQPChannel as BaseAMQPChannel;

class AMQPChannel extends BaseAMQPChannel
{

    private $next_delivery_tag = 0;

    /**
     * Puts the channel into confirm mode
     * Beware that only non-transactional channels may be put into confirm mode and vice versa
     *
     * @param bool $nowait
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException if the specified operation timeout was exceeded
     */
    public function confirm_select($nowait = false)
    {
        list($class_id, $method_id, $args) = $this->protocolWriter->confirmSelect($nowait);

        $this->send_method_frame(array($class_id, $method_id), $args);

        if ($nowait) {
            return null;
        }

        $this->wait(array(
            $this->waitHelper->get_wait('confirm.select_ok')
        ), false, $this->channel_rpc_timeout);
        if ($this->next_delivery_tag == 0)
            $this->next_delivery_tag = 1;
    }

}