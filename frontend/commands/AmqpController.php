<?php

namespace app\commands;

use app\models\CountJob;
use app\models\RequestJob;
use pzr\amqp\AmqpBase;
use pzr\amqp\event\PushEvent;
use pzr\amqp\jobs\RpcJob;
use Yii;
use yii\console\Controller;


class AmqpController extends Controller
{
    /**
     * 启动amqp的普通消费者：php yii amqp/consumer queueName
     *
     * @param string $queueName
     * @param integer $qos
     * @param string $consumerTag
     * @return void
     */
    public function actionConsumer($queueName, $qos=1, $consumerTag='')
    {
        /*Yii::$app->consumer->on(AmqpBase::EVENT_BEFORE_EXEC, function(ExecEvent $event){
            // 消费者准备消费
        });

        Yii::$app->consumer->on(AmqpBase::EVENT_AFTER_EXEC, function(ExecEvent $event){
            // 消费者准备消费
        });*/

        Yii::$app->consumer->consume($queueName, $qos, $consumerTag);
    }

    /**
     * 启动amqp的RPC消费者：php yii amqp/rpc-consumer queueName
     *
     * @param string $queueName
     * @param integer $qos
     * @param string $consumerTag
     * @return void
     */
    public function actionRpcConsumer($queueName, $qos=1)
    {
        Yii::$app->rpcConsumer->consume($queueName, $qos);
    }
    
    /**
     * RPC 批量发送请求
     * 
     * @return void
     */
    public function actionRpcServe($jobs, $qos=1, $timeout=3) {
        if (empty($jobs) || !is_array($jobs)) {
            return false;
        }

        Yii::$app->rpcQueue->on(AmqpBase::EVENT_BEFORE_PUSH, function(PushEvent $event) {
            Yii::$app->rpcQueue->bind();
        });

        /**
         * 既能支持单条又能支持批量
         */
        $response = Yii::$app->rpcQueue->setQos($qos)->setTimeout($timeout)->publish($jobs);
        return $response;
    }

}
