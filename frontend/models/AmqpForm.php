<?php

namespace app\models;

use Exception;
use Pheanstalk\Pheanstalk;
use pzr\amqp\Amqp;
use pzr\amqp\api\AmqpApi;
use pzr\amqp\cli\Consumer;
use pzr\amqp\cli\helper\AmqpIni;
use pzr\amqp\cli\helper\FileHelper;
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

    public function getAmqpIni() {
        $array = AmqpIni::readIni();
        $access_log = AmqpIni::findRealpath($array['common']['access_log']);
        $is_access_log_writable = is_writeable($access_log);
        $is_access_log_readable = is_readable($access_log);

        $error_log = AmqpIni::findRealpath($array['common']['error_log']);
        $is_error_log_writable = is_writeable($error_log);
        $is_error_log_readable = is_readable($error_log);

        $pipe_file = AmqpIni::findRealpath($array['pipe']['pipe_file']);
        $is_pipe_writable = is_writable($pipe_file);
        $is_pipe_readable = is_readable($pipe_file);

        $pidfile = AmqpIni::findRealpath($array['common']['pidfile']);
        $is_pidfile_writable = is_writable($pidfile);
        $is_pidfile_readable = is_readable($pidfile);

        $process_file = AmqpIni::findRealpath($array['common']['process_file']);
        $is_process_file_writable = is_writable($process_file);
        $is_process_file_readable = is_readable($process_file);

        $default_access_log = DEFAULT_ACCESS_LOG;
        $is_default_access_log_writable = is_writable(DEFAULT_ACCESS_LOG);
        $is_default_access_log_readable = is_readable(DEFAULT_ACCESS_LOG);

        $default_error_log = DEFAULT_ERROR_LOG;
        $is_default_error_log_writable = is_writable(DEFAULT_ERROR_LOG);
        $is_default_error_log_readable = is_readable(DEFAULT_ERROR_LOG);

        $unix = AmqpIni::findRealpath($array['common']['unix']);
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        $isConn = 0;
        if (socket_connect($socket, $unix)) {
            $isConn = true;
            socket_close($socket);
        }
        
        $amqp = $array['amqp'];
        $redis = $array['redis'];
        $beanstalk = $array['beanstalk'];

        $isAmqpConn = (new AmqpApi($amqp))->checkActive();

        try {
            $redisObj = new \Redis();
            $redisObj->connect($redis['host'], $redis['port']);
            $redisObj->auth($redis['password']);
        } catch (Exception $e) {
            $redisObj = null;
        }
        $isRedisActive = empty($redisObj) ? false : true;

        $talker = Pheanstalk::create($beanstalk['host'], $beanstalk['port']);
        $isBeanstalkActive = empty($talker) ? false : true;

        $handler = $array['handler']['class'];
        $commun = $array['communication']['class'];

        $parseIni = <<<EOF
【default】
default_access_log = $default_access_log 【is_writable: $is_default_access_log_writable is_readable: $is_default_access_log_readable 】
default_error_log = $default_error_log 【is_writable: $is_default_error_log_writable is_readable: $is_default_error_log_readable 】

【check file】
access_log = $access_log  【is_writable: $is_access_log_writable is_readable: $is_access_log_readable 】
error_log = $error_log  【is_writable: $is_error_log_writable is_readable: $is_error_log_readable 】
pipe_file = $pipe_file  【is_writable: $is_pipe_writable is_readable: $is_pipe_readable 】
pidfile = $pidfile  【is_writable: $is_pidfile_writable is_readable: $is_pidfile_readable 】
process_file = $process_file  【is_writable: $is_process_file_writable is_readable: $is_process_file_readable 】

【server】
unix = $unix
isConn = $isConn

【check connection】
isAmqpActive = $isAmqpConn
isRedisActive = $isRedisActive
isBeanstalkActive = $isBeanstalkActive

【select】
handle.class = $handler
commun.class = $commun
EOF;


        $sourceIni = FileHelper::read(DEFALUT_AMQPINI_PATH);

        return [$sourceIni, $parseIni];
    }
}
