<?php
/**
 * 普通ACK策略
 * 当前任务处理失败时，默认requeue
 * 
 * @author ruansizhe
 * @date 2021-06-02
 */
namespace pzr\amqp\ack;

class PolicyAckNormal implements AckPolicyInterface {

    public function noAck(){
        return false;
    }
   
    public function handleSucc($payload, $event){
        $payload->ack(true);
    }

    public function handleFail($payload, $event){
        $payload->nack(true, true);
    }

    public function handleError($payload, $event){
        $payload->nack(true, true);
    }




}
