<?php

namespace pzr\amqp\ack;

interface AckPolicyInterface {

    public function noAck();

    public function handleSucc($payload, $event);

    public function handleFail($payload, $event);

    public function handleError($payload, $event);

}

?>