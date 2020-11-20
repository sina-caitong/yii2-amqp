<?php

namespace pzr\amqp;

use pzr\amqp\exception\InvalidArgumentException;
use pzr\amqp\serializers\PhpSerializer;
use pzr\amqp\serializers\SerializerInterface;
use yii\base\ExitException;
use yii\console\Application;
use yii\console\Request;
use yii\di\Instance;

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');
defined('YII_CONSOLE_PATH') or define('YII_CONSOLE_PATH', '');

$baseDir = include dirname(__DIR__) . '/FindVendor.php';

require $baseDir . '/vendor/autoload.php';
require $baseDir . '/vendor/yiisoft/yii2/Yii.php';

class MyYii
{
    private $_application;
    private $_request;

    /**
     * @var SerializerInterface|array
     */
    public $serializer = PhpSerializer::class;

    public function __construct($config=[])
    {
        /** 初始化序列化对象 */
        $this->serializer = Instance::ensure($this->serializer, SerializerInterface::class);
        // 摆脱每次加载console.php的困扰
        if (is_file(YII_CONSOLE_PATH)) {
            $config = require YII_CONSOLE_PATH;
        }
        if (empty($config) || !is_array($config)) {
            throw new InvalidArgumentException('invalid config');
        }
        $this->_application = new Application($config);
        $this->_request = new Request();
        $this->_application->trigger(Application::EVENT_BEFORE_REQUEST);
    }

    /**
     * 发起Console请求，RPC时调用
     *
     * @param array $reqParams
     * @return Response|bool
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
            $rpcResponse = $response->exitStatus;
            if (!($rpcResponse instanceof Response)) {
                return $rpcResponse;
            }

            $result = $rpcResponse->getResponse();

            /* 单个请求 */
            if (!is_array($result)) {
                return $this->serializer->unserialize($result);
            }

            /* 批量请求 */
            $results = [];
            foreach( $result as $corrid => $r ) {
                $results[$corrid] = $this->serializer->unserialize($r);
            }
            return $results;
        } catch (ExitException $e) {
            $this->_application->end($e->statusCode, isset($response) ? $response : null);
            return false;
        }
    }

    /**
     * 设置序列化对象
     *
     * @param SerializerInterface $serializer
     * @return void
     */
    public function setSerializer(SerializerInterface $serializer) {
        $this->serializer = Instance::ensure($serializer, SerializerInterface::class);
    }

    public function __destruct()
    {
        // 对象销毁的时候会触发AMQP连接的关闭
        $this->_application->trigger(Application::EVENT_AFTER_REQUEST);
    }
}
