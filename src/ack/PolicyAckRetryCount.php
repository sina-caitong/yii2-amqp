<?php
/**
 * 重试次数计数策略
 * 会对当前任务的失败次数作计数统计，当失败次数超过一定数值时，nack放弃处理
 * 
 * @author ruansizhe
 * @date 2021-06-02
 */
namespace pzr\amqp\ack;

use Common;

class PolicyAckRetryCount implements AckPolicyInterface {

    public static $retryCountArr = [];
    public $retryLimit = 3;

    public function noAck(){
        return false;
    }
   
    public function handleSucc($payload, $event){
        $payload->ack(true);
    }

    public function handleFail($payload, $event){
        // 获取消息ID
        $msgId = $payload->get('message_id');
        !isset( self::$retryCountArr[$msgId] ) && self::$retryCountArr[$msgId] = 0;
        // 重试计数+1
        self::$retryCountArr[$msgId]++;
        Common::debug("$msgId: " . self::$retryCountArr[$msgId]);
        
        if( self::$retryCountArr[$msgId] < $this->retryLimit ){
            $payload->nack(true, true);
        } 
        else{
            $payload->nack(false);
        }
        
    }

    public function handleError($payload, $event){
        $this->handleFail( $payload, $event );
    }



}
