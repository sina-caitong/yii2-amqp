<?php

namespace pzr\amqp\api;

use Exception;
use pzr\amqp\exception\InvalidArgumentException;
use Symfony\Component\HttpClient\HttpClient;
use yii\base\Component;

class AmqpApi extends Component
{

    protected static $http;
    public $vhost = '/';
    public $host = '127.0.0.1';
    public $port = 5672;
    public $apiPort = 15672;
    public $apiUri;
    public $user = 'guest';
    public $password = 'guest';

    public function init()
    {
        self::$http = HttpClient::create();
        $this->vhost = urlencode($this->vhost);
        if (empty($this->apiUri)) {
            $this->apiUri = sprintf("http://%s:%s/api", $this->host, $this->apiPort);
        }
        parent::init();
    }

    /**
     * http://127.0.0.1:15672/api/queues/%2F/queueName/bindings
     *
     * @return void
     */
    public function getBinding($queueName)
    {
        if (empty($queueName)) {
            $this->handleError('queueName');
        }
        $reqUrl = $this->formatApiUrl('queues', $queueName . '/bindings');
        $options = $this->getHttpOptions();
        $data = self::$http->request('GET', $reqUrl, $options)->getContent();
        return json_decode($data, true);
    }

    /**
     * eg：http://127.0.0.1:15672/api/queues/%2F/queueName
     * @param $queueName
     * @param string $vhost
     * @return array
     */
    public function getInfosByQueue($queueName)
    {
        if (empty($queueName)) {
            $this->handleError('queueName');
        }
        $reqUrl = $this->formatApiUrl('queues', $queueName);
        $options = $this->getHttpOptions();
        $data = self::$http->request('GET', $reqUrl, $options)->getContent();
        return json_decode($data, true);
    }


    /**
     * eg：http://127.0.0.1:15672/api/exchanges/%2F/exchangeName
     * @param $queueName
     * @param string $vhost
     * @return array
     */
    public function getInfosByExchange($exchangeName)
    {
        if (empty($exchangeName)) {
            $this->handleError('exchangeName');
        }
        $reqUrl = $this->formatApiUrl('exchanges', $exchangeName);
        $options = $this->getHttpOptions();
        $data = self::$http->request('GET', $reqUrl, $options)->getContent();
        return json_decode($data, true);
    }

    public function closeConnection($connectionName)
    {
        if (empty($connectionName)) {
            $this->handleError('connectionName');
        }
        $this->vhost = '';
        $connectionName = urlencode($connectionName);
        $reqUrl = $this->formatApiUrl('connections', $connectionName);
        $options = $this->getHttpOptions([
            'headers' => [
                'X-Reason : delete by AMQP MANAGER'
            ]
        ]);
        $data = self::$http->request('DELETE', $reqUrl, $options)->getContent();
        return json_decode($data, true);
    }

    /**
     * 检验vhost的状态
     * 原理是amqp会发送一个消息到内置的queue，即aliveness-test
     * eg：http://127.0.0.1:15672/api/aliveness-test/%2F
     * @param string $vhost
     * @return bool
     */
    public function checkActive()
    {
        $reqUrl = $this->formatApiUrl('aliveness-test');
        $options = $this->getHttpOptions();
        try {
            $data = self::$http->request('GET', $reqUrl, $options)->getContent();
            $statusArr = json_decode($data, true);
            $status = $statusArr['status'];
            if ($status == 'ok') {
                return true;
            }
        } catch (Exception $e) {
            return false;
        }
        return false;
    }


    /**
     * RabbitMQ的内存使用情况
     * eg：http://127.0.0.1:15672/api/nodes
     */
    public function monityMemory()
    {
        $reqUrl = $this->formatApiUrl('nodes');
        $json = self::$http->request('GET', $reqUrl)->getContent();
        $data = json_decode($json, true);
        return $data;
    }

    protected function formatApiUrl($apiName, $reqSuffix = '')
    {
        return sprintf("%s/%s/%s/%s", $this->apiUri, $apiName, $this->vhost, $reqSuffix);
    }

    protected function handleError($filed)
    {
        throw new InvalidArgumentException('Unexcept value about ' . $filed);
    }

    public function getHttpOptions(array $httpOptions = [])
    {
        $options = [
            'headers' => ['Content-Type: application/json'],
            'auth_basic' => $this->user . ':' . $this->password,
        ];

        return array_merge($options, $httpOptions);
    }
}
