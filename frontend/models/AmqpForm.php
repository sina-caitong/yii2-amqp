<?php

namespace app\models;

use pzr\amqp\Amqp;
use pzr\amqp\api\AmqpApi;
use pzr\amqp\cli\Consumer;
use pzr\amqp\cli\helper\AmqpIni;
use pzr\amqp\cli\helper\ProcessHelper;
use yii\base\Model;

class AmqpForm extends Model
{

    public function traceLog($limit = 100)
    {
        list($access_log, $error_log, $level) = AmqpIni::getDefaultLogger();
        $access_log = $this->findRealPath($access_log);
        $error_log = $this->findRealPath($error_log);

        $array = AmqpIni::readIni();
        $amqp = $array['amqp'];
        $redis = $array['redis'];
        $beanstalk = $array['beanstalk'];
        $pipe = $array['pipe'];

        $amqp_access_log = isset($amqp['access_log']) && $amqp['access_log'] != $access_log ? $amqp['access_log'] : '';
        $amqp_error_log = isset($amqp['error_log']) && $amqp['error_log'] != $error_log ? $amqp['error_log'] : '';

        $redis_access_log = isset($redis['access_log']) && $redis['access_log'] != $access_log ? $redis['access_log'] : '';
        $redis_error_log = isset($redis['error_log']) && $redis['error_log'] != $error_log ? $redis['error_log'] : '';

        $beanstalk_access_log = isset($beanstalk['access_log']) && $beanstalk['access_log'] != $access_log ? $beanstalk['access_log'] : '';
        $beanstalk_error_log = isset($beanstalk['error_log']) && $beanstalk['error_log'] != $error_log ? $beanstalk['error_log'] : '';

        $pipe_access_log = isset($pipe['access_log']) && $pipe['access_log'] != $access_log ? $pipe['access_log'] : '';
        $pipe_error_log = isset($pipe['error_log']) && $pipe['error_log'] != $error_log ? $pipe['error_log'] : '';

        return [
            'level' => $level,
            'logs' => [
                $access_log,
                $error_log,
                $amqp_access_log,
                $amqp_error_log,
                $redis_access_log,
                $redis_error_log,
                $beanstalk_access_log,
                $beanstalk_error_log,
                $pipe_access_log,
                $pipe_error_log
            ]
        ];
    }

    private function findRealPath($path)
    {
        if (preg_match('/%Y|%y|%d|%m/', $path)) {
            $path = str_replace(['%Y', '%y', '%m', '%d'], [date('Y'), date('y'), date('m'), date('d')], $path);
            $path = AmqpIni::findRealpath($path);
        }
        return $path;
    }

    public function statV2()
    {
        $files = AmqpIni::getConsumerFile();
        $config = AmqpIni::readAmqp();
        $api = new AmqpApi($config);
        $statFile = $this->statFile();

        $default = [
            'declare' => false,
            'nums' => 0,
            'processNums' => 0,
            'qos' => 1,
            'ack' => false,
            'connections' => []
        ];
        $queueArray = $consumerArray = [];

        foreach ($files as $file) {
            $config = parse_ini_file($file);
            $consumer = new Consumer($config);
            foreach ($consumer->getQueues() as $c) {
                $queue = $c->queueName;
                if (isset($queueArray[$queue])) continue;
                $queueArray[$queue] = 1;
                $info = $api->getInfosByQueue($queue);
                $isDeclare = isset($info['consumer_details']) ? true : false;
                $consumerArray[$queue]['isDeclare'] = $isDeclare;
                if (!$isDeclare) continue;
                $detail = $info['consumer_details'];
                $connections = [];
                foreach ($detail as $v) {
                    $connections[] = [
                        'qos' => $v['prefetch_count'],
                        'ack' => $v['ack_required'],
                        'connection' => $v['channel_details']['connection_name'],
                    ];
                }
                $consumerArray[$queue]['connections'] = array_merge(
                    isset($consumerArray[$queue]['connections']) ? $consumerArray[$queue]['connections'] : [],
                    $connections
                );
                $consumerArray[$queue]['nums'] = count($consumerArray[$queue]['connections']);
                $consumerArray[$queue]['processNums'] = isset($statFile[$queue]) ? $statFile[$queue] : 0;
            }
        }

        foreach ($consumerArray as $k => $c) {
            $c = array_intersect_key($c, $default);
            $consumerArray[$k] = $c;
        }

        return $consumerArray;
    }

    public function statFile()
    {
        $array = ProcessHelper::read();
        $stat = array();
        foreach ($array as $v) {
            list($pid, $ppid, $queueName, $program) = $v;
            // $str = substr($queueName, -2);
            // $queue = preg_match('/^_\d$/', $str) ? substr($queueName, 0, -2) : $queueName;
            isset($stat[$queueName]) ? $stat[$queueName]++ : $stat[$queueName] = 1;
        }
        return $stat;
    }



    public function list()
    {
        $array = ProcessHelper::read();
        $list = array();
        $stat = array();
        $color = array();
        $domainCheck = array();
        foreach ($array as $v) {
            list($pid, $ppid, $queueName, $program) = $v;
            $isAlive = $domainCheck[$ppid] = isset($domainCheck[$ppid]) ? $domainCheck[$ppid] : AmqpIni::checkProcessAlive($ppid);
            $ppid = $isAlive ? $ppid : 1;
            $str = substr($queueName, -2);
            $queue = preg_match('/^_\d$/', $str) ? substr($queueName, 0, -2) : $queueName;
            isset($stat[$queue]) ? $stat[$queue]++ : $stat[$queue] = 1;
            $color[$queue] = isset($color[$queue]) ? $color[$queue] : $this->getColor();
            $list[][] = [
                'pid' => $pid,
                'ppid' => $ppid,
                'queueName' => $queueName,
                'program' => $program,
                'color' => $color[$queue],
                'ppidAlive' => $isAlive,
            ];
        }

        uksort($stat, function ($a, $b) {
            return $a == $b ? 0 : ($a > $b ? 1 : -1);
        });

        return [$list, $stat, $color];
    }

    public function getColor()
    {
        $array = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 'A', 'B', 'C', 'D', 'E', 'F'];
        $color = '#';
        for ($i = 0; $i < 6; $i++) {
            $color .= $array[mt_rand(0, count($array) - 1)];
        }
        return ''; //$color;
    }
}
