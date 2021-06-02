<?php
/**
 * 无需ACK策略
 * 无需ACK
 * 
 * @author ruansizhe
 * @date 2021-06-02
 */
namespace pzr\amqp\ack;

class PolicyNoAck implements AckPolicyInterface {

    public function noAck(){
        return true;
    }
   
    public function handleSucc($payload, $event){

    }

    public function handleFail($payload, $event){

    }

    public function handleError($payload, $event){
        
    }
}
