<?php

namespace app\commands;

use app\models\CountJob;
use app\models\RequestJob;
use pzr\amqp\AmqpBase;
use pzr\amqp\AmqpJob;
use pzr\amqp\event\ExecEvent;
use pzr\amqp\event\PushEvent;
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
    public function actionConsumer($queueName, $qos=1)
    {
        /*Yii::$app->consumer->on(AmqpBase::EVENT_BEFORE_EXEC, function(ExecEvent $event){
            // 消费者准备消费
        });

        Yii::$app->consumer->on(AmqpBase::EVENT_AFTER_EXEC, function(ExecEvent $event){
            // 消费者准备消费
        });*/

        Yii::$app->consumer->consume($queueName, $qos);
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
     * 普通队列定义
     *
     * @return void
     */
    public function actionEasy() {
        Yii::$app->easyQueue->on(AmqpBase::EVENT_BEFORE_PUSH, function(PushEvent $event) {
            Yii::$app->easyQueue->bind();   //绑定队列，如果已经绑定可以注释此方法
            // $event->noWait = true;          //默认false开启客户端消息确认机制，true则关闭
        });

        /* Yii::$app->easyQueue->on(AmqpBase::EVENT_PUSH_ACK, function(PushEvent $event) {
            // 客户端发送消息确认成功
        });

        Yii::$app->easyQueue->on(AmqpBase::EVENT_PUSH_NACK, function(PushEvent $event) {
            // 客户端发送消息确认失败
        });*/

        // 发送单条消息
        // Yii::$app->easyQueue->push(new CountJob([
        //     'count' => 1,
        // ]));

        // 批量发送
        for ($i=1; $i<=10; $i++) {
            $jobs[] = new CountJob([
                'count' => $i
            ]);
        }
        Yii::$app->easyQueue->myPublishBatch($jobs);
    }

    /**
     * 延时队列定义
     *
     * @return void
     */
    public function actionDelay() {
        Yii::$app->delayQueue->on(AmqpBase::EVENT_BEFORE_PUSH, function(PushEvent $event) {
            Yii::$app->delayQueue->bind();
        });

        // 批量发送
        for ($i=1; $i<=10; $i++) {
            $jobs[] = new CountJob([
                'count' => $i
            ]);
        }
        Yii::$app->delayQueue->myPublishBatch($jobs, '07_14_delay.test.');
    }

    /**
     * RPC队列定义
     *
     * @return void
     */
    public function actionRpc() {
        Yii::$app->rpcQueue->on(AmqpBase::EVENT_BEFORE_PUSH, function(PushEvent $event) {
            Yii::$app->rpcQueue->bind();
        });

        // 批量请求
        for ($i=1; $i<=10; $i++) {
            $jobs[] = new RequestJob([
                'request' => 'request_' . $i,
            ]);
        }
        $response = Yii::$app->rpcQueue->setQos(10)->myPublishBatch($jobs);
        return $response;
    }

    /**
     * 只是一个DEMO，无法正常运行
     *
     * @return void
     */
    public function actionRpcServe($jobs, $qos=1, $timeout=3) {
        if (empty($jobs) || !is_array($jobs)) {
            return false;
        }
        Yii::$app->serveQueue->on(AmqpBase::EVENT_BEFORE_PUSH, function(PushEvent $event) {
            Yii::$app->serveQueue->bind();
        });

        /**
         * 这里定义为批量发送是因为既能支持单条又能支持批量
         */
        $response = Yii::$app->serveQueue->setQos($qos)->setTimeout($timeout)->myPublishBatch($jobs);
        return $response;
    }

}
