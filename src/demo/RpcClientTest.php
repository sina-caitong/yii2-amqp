<?php

use app\models\ServeJob;
use pzr\amqp\Yii;

/**
 * @example DEMO  模拟Rpc请求方远程调用
 */
class RpcClientTest
{

    public function callServe() {
        /**
         * 实际调用的是
         * ```php
         * $test = new Test();
         * $test->test($say);
         * ```
         */ 
        $job = new ServeJob([
            'object' => 'Test',
            'action' => 'test',
            'params' => [
                'say' => 'Hello World!'
            ],
            // 'uuid' => uniqid(true), //如果是批量请求并且希望跟踪某条消息的返回结果时
        ]);
        // 在请求前 define('YII_CONSOLE_PATH', '\path');
        $yii = new Yii();
        $response = $yii->request([
            'amqp/serve',
            $job
        ]);
        var_dump($response);
    }

}