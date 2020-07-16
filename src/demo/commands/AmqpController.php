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
        Yii::$app->consumer->on(AmqpBase::EVENT_BEFORE_EXEC, function(ExecEvent $event){
            // 消费者准备消费
            $this->myLog('BEFORE Consume the number of ' . $event->job->count);
        });

        Yii::$app->consumer->on(AmqpBase::EVENT_AFTER_EXEC, function(ExecEvent $event){
            // 消费者准备消费
            $this->myLog('AFTER Consume the number of ' . $event->job->count);
        });

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
            $this->myLog('easyQueue Bind OK');
        });

        Yii::$app->easyQueue->on(AmqpBase::EVENT_PUSH_ACK, function(PushEvent $event) {
            // 客户端发送消息确认成功
            $this->myLog('ACK the number of ' . $event->job->count);
        });

        Yii::$app->easyQueue->on(AmqpBase::EVENT_PUSH_NACK, function(PushEvent $event) {
            // 客户端发送消息确认失败
            $this->myLog('NACK the number of ' . $event->job->count);
        });

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
            Yii::$app->delayQueue->bind();   //绑定队列，如果已经绑定可以注释此方法
            // $event->noWait = true;        //默认false开启客户端消息确认机制，true则关闭
            $this->myLog('delayQueue Bind OK');
        });

        Yii::$app->delayQueue->on(AmqpBase::EVENT_PUSH_ACK, function(PushEvent $event) {
            // 客户端发送消息确认成功
            $this->myLog('ACK the number of ' . $event->job->count);
        });

        Yii::$app->delayQueue->on(AmqpBase::EVENT_PUSH_NACK, function(PushEvent $event) {
            // 客户端发送消息确认失败
            $this->myLog('NACK the number of ' . $event->job->count);
        });

        // 发送单条消息
        // Yii::$app->delayQueue->push(new CountJob([
        //     'count' => 1,
        // ]));

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
            Yii::$app->rpcQueue->bind();   //绑定队列，如果已经绑定可以注释此方法
            // $event->noWait = true;        //默认false开启客户端消息确认机制，true则关闭
            $this->myLog('rpcQueue Bind OK');
        });

        Yii::$app->rpcQueue->on(AmqpBase::EVENT_PUSH_ACK, function(PushEvent $event) {
            // 客户端发送消息确认成功
            $this->myLog('ACK the number of ' . $event->job->request);
        });

        Yii::$app->rpcQueue->on(AmqpBase::EVENT_PUSH_NACK, function(PushEvent $event) {
            // 客户端发送消息确认失败
            $this->myLog('NACK the number of ' . $event->job->request);
        });

        // 单条请求
        // $response = Yii::$app->rpcQueue->push(new RequestJob(
        //     ['request' => 'request']
        // ));

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
     * MyYii 的RPC 调用测试
     *
     * @param array $jobs  传的第一个参数是数组，却不能在方法定义中申明：array jobs 。
     * 因为在yii\console\Controller 中判断：如果申明是数组，那么会用逗号隔开。
     * @param integer $qos
     * @param integer $timeout
     * @return void
     */
    public function actionRpcTest($jobs, $qos=1, $timeout=3) {
        // 单条请求
        // $response = Yii::$app->rpcQueue->push(new RequestJob(
        //     ['request' => 'request']
        // ));
        if (empty($jobs)) {
            return null;
        }
        $response = Yii::$app->rpcQueue->setQos($qos)
            ->setTimeout($timeout)
            ->myPublishBatch($jobs);
        return $response;
    }


    /**
     * 普通队列定义
     *
     * @return void
     */
    public function actionEasyTopic() {
        Yii::$app->easyQueueTopic->on(AmqpBase::EVENT_BEFORE_PUSH, function(PushEvent $event) {
            Yii::$app->easyQueueTopic->bind();   //绑定队列，如果已经绑定可以注释此方法
            // $event->noWait = true;          //默认false开启客户端消息确认机制，true则关闭
        });

        // 发送单条消息
        Yii::$app->easyQueueTopic->push(new CountJob([
            'count' => 1,
        ]));
    }

    public function myLog($msg) {
        @error_log(date('Y-m-d H:i:s') . '：' . $msg . PHP_EOL, 3, dirname(__DIR__) . '/runtime/logs/test.log');
    }
}
