<?php

namespace pzr\amqp\jobs;

use pzr\amqp\Response;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * 被调用方实现execute的实际响应方法，并且在被调用方的工作环境下启动RPC消费者
 */
class RpcJob extends AmqpJob
{
    /** 
     * 1、如果追求客户端调用通用性比较好，可以定义请求的对象是什么，
     * 并且由服务端实例化该对象并且调用实际的响应方法。对于调用方来说需要提前知道该对象的命名空间及实例化
     * 2、服务端和客户端协议好哪些对象可被调用，然后客户端就不需要定义这个属性。
     */
    public $object;
    /** 实际响应的方法 */
    public $action;
    /** 请求法的参数 */
    public $params;
    /** 父类构造方法参数 */
    public $args = array();

    public function execute()
    {
        if (empty($this->object) || empty($this->action)) {
            return $this->debug ? Response::setError(1) : false;
        }
        if (!class_exists($this->object)) {
            return $this->debug ? Response::setError(4, $this->object) : false;
        }
        $reflect = new ReflectionMethod($this->object, $this->action);
        $flag = $reflect->isStatic();
        if ($flag) {
            return $reflect->invokeArgs(null, $this->params);
        }

        try {
            $class = new ReflectionClass($this->object);
            if (empty($this->args)) {
                $obj = $class->newInstance();
            } elseif (!is_array($this->args)) {
                $obj = $class->newInstance($this->args);
            } else {
                $obj = $class->newInstanceArgs($this->args);
            }
        } catch(ReflectionException $e) {
            return Response::setError(5, $e->getMessage());
        }
        
        if (!is_object($obj)) return Response::setError(2);
        if (!method_exists($obj, $this->action)) {
            return $this->debug ? Response::setError(3) : false;
        }
        return call_user_func_array([$obj, $this->action], $this->params);
    }
}