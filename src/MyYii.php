<?php

namespace pzr\amqp;

use pzr\amqp\exception\InvalidArgumentException;
use yii\base\ExitException;
use yii\console\Application;
use yii\console\Request;

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

$baseDir = dirname(__DIR__);
if (!is_file( $baseDir . '/vendor/autoload.php' )) {
    $baseDir = dirname(__DIR__, 4);
}

require $baseDir . '/vendor/autoload.php';
require $baseDir . '/vendor/yiisoft/yii2/Yii.php';

class MyYii
{
    private $_application;
    private $_request;
    public $config;

    public function __construct($config)
    {
        $this->_application = new Application($config);
        $this->_request = new Request();
        $this->_application->trigger(Application::EVENT_BEFORE_REQUEST);
    }

    /**
     * 发起Console请求，RPC时调用
     *
     * @param array $reqParams
     * @return Response
     */
    public function request(array $reqParams)
    {
        if (empty($reqParams) || !is_array($reqParams)) {
            throw new InvalidArgumentException('invalid reqParams');
        }
        // 在请求的路由前增加--后，$reqParams的参数可以直接传递到action对应的参数
        if ($reqParams[0] !== '--') array_unshift($reqParams, '--');
        $this->_request->setParams($reqParams);
        try {
            $response = $this->_application->handleRequest($this->_request);
            $response->send();
            return $response->exitStatus;
        } catch (ExitException $e) {
            $this->_application->end($e->statusCode, isset($response) ? $response : null);
            return false;
        }
    }

    public function __destruct()
    {
        // 对象销毁的时候会触发AMQP连接的关闭
        $this->_application->trigger(Application::EVENT_AFTER_REQUEST);
    }
}