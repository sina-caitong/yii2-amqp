<?php

namespace pzr\amqp;

use yii\base\BaseObject;

class Response extends BaseObject
{

    public $response;

    public static $error = [
        0 => 'SUCCESS',
        1 => 'object|action is empty',
        2 => 'object instance error',
        3 => 'action is\'t exist',
        4 => 'object is\'t exist',
        10 => 'other uncatch error',
    ];


    public static function setError($errno, $msg = '')
    {
        $msg = empty($msg) && isset(self::$error[$errno])
            ? self::$error[$errno]
            : 'unkonw error';
        return [
            'rpc_errno' => $errno,
            'msg' => $msg
        ];
    }

    public static function checkResponse($result)
    {
        if (!is_array($result)) {
            return $result;
        }
        if (isset($result['rpc_errno']) && $result['rpc_errno'] != 0) {
            return false;
        }
        return $result;
    }

    /**
     * Get the value of response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Set the value of response
     *
     * @return  self
     */
    public function setResponse($response)
    {
        $this->response = $response;

        return $this;
    }
}
