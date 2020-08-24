<?php

namespace pzr\amqp\cli\helper;

use Exception;
use Monolog\Logger as BaseLogger;
use pzr\amqp\api\AmqpApi;
use pzr\amqp\cli\helper\ProcessHelper;
use pzr\amqp\cli\logger\Logger;

@is_dir('/usr/local/var/run') ?: mkdir('/usr/local/var/run', 0777, true);

defined('DEFALUT_AMQPINI_PATH') or define('DEFALUT_AMQPINI_PATH', __DIR__ . '/../config/amqp.ini');
defined('DEFAULT_PIDFILE_PATH') or define('DEFAULT_PIDFILE_PATH', '/usr/local/var/run/amqp_master.pid');
defined('DEFAULT_ACCESS_LOG') or define('DEFAULT_ACCESS_LOG', __DIR__ . '/../log/access.log');
defined('DEFAULT_ERROR_LOG') or define('DEFAULT_ERROR_LOG', __DIR__ . '/../log/error.log');

class AmqpIni
{
    /** @var amqp.ini读取的内容 */
    protected static $array = array();
    /** @var \pzr\amqp\api\AmqpApi */
    protected static $amqpApi = null;
    /** @var array 待启动的消费者数组 */
    protected static $queueArray = array();
    /** @var array 进程通信方式映射 */
    public static $communClassMap = [
        'pipe' => \pzr\amqp\cli\Communication\PipeCommun::class,
    ];
    /** @var array 进程文件管理方式映射 */
    public static $handlerClassMap = [
        'beanstalk' => \pzr\amqp\cli\handler\BeanstalkHandler::class,
        'redis' => \pzr\amqp\cli\handler\RedisHandler::class,
        'amqp' => \pzr\amqp\cli\handler\AmqpHandler::class
    ];

    /** @var \pzr\amqp\cli\logger\Logger */
    public static $logger = null;

    /** @var string 默认读取的日志级别 */
    const DEFAULT_LOGGER_LEVEL = 'info,error,warning';


    public static function parseIni()
    {
        $array = static::readIni();
        $amqp = static::readAmqp();
        $files = static::readInclude($array);
        return [
            'amqp' => $amqp,
            // 两种思路了已经：
            // files按消费者的配置文件启动消费者
            // queues是综合了所有的消费者配置文件，并且过滤了已经创建的消费者
            'files' => $files,
            'queues' => static::$queueArray,
        ];
    }

    public static function readHandler()
    {
        $array = static::readIni();
        isset($array['handler']) or static::exit('read [handler] module failed');
        $handlerClass = $array['handler']['class'];
        array_key_exists($handlerClass, static::$handlerClassMap)
            or static::exit('invalid value of [handler.class]: ' . $handlerClass);
        isset($array[$handlerClass]) or static::exit('read [' . $handlerClass . '] module failed');
        $handlerArray = $array[$handlerClass];
        $handlerArray['class'] = self::$handlerClassMap[$handlerClass];
        static::loggerHelp($handlerArray);
        return $handlerArray;
    }


    public static function readAmqp()
    {
        $array = static::readIni();
        isset($array['amqp']) or static::exit('read [amqp] module failed');
        // 校验AMQP服务是否正常连接
        try {
            $amqpApi = new AmqpApi($array['amqp']);
        } catch (Exception $e) {
            static::exit($e->getMessage());
        }

        $amqpApi->checkActive() or static::exit('AMQP Serve connected failed');
        static::$amqpApi = $amqpApi;
        return $array['amqp'];
    }

    public static function getDefaultLogger()
    {
        $common = static::readCommon();
        $access_log = isset($common['access_log']) ? static::findRealpath($common['access_log']) : DEFAULT_ACCESS_LOG;
        $error_log = isset($common['error_log']) ? static::findRealpath($common['error_log']) : DEFAULT_ERROR_LOG;
        $level = isset($common['level']) ?  $common['level'] : static::DEFAULT_LOGGER_LEVEL;
        return [$access_log, $error_log, $level];
    }

    public static function loggerHelp(&$array)
    {
        $access_log_tmp = isset($array['access_log']) ? $array['access_log'] : '';
        $error_log_tmp = isset($array['error_log']) ? $array['error_log'] : '';
        list($access_log, $error_log, $level) = static::getDefaultLogger();
        $access_log = static::findRealpath($access_log_tmp) ?: $access_log;
        $error_log = static::findRealpath($error_log_tmp) ?: $error_log;
        $level = isset($array['level']) ? $array['level'] : $level;
        $array['access_log'] = $access_log;
        $array['error_log'] = $error_log;
        $array['level'] = $level;
    }

    public static function findRealpath(string $filepath, bool $touch = true)
    {
        if (empty($filepath)) {
            return false;
        }
        if (strncmp($filepath, '.', 1)) {
            if ($touch && !is_file($filepath) && !@touch($filepath)) {
                static::addLog($filepath . ' touch failed', BaseLogger::ERROR);
                return false;
            }
            return $filepath;
        }
        // 匹配最后一个符号/（包括）到末尾位置
        $str = strrchr(DEFALUT_AMQPINI_PATH, '/');
        $basePath = str_replace($str, '', DEFALUT_AMQPINI_PATH);
        $realpath = $basePath . '/' . $filepath;
        if (!$touch) return $realpath;
        // 由于文件地址中包含变量，所以必须在写文件的时候在替换变量
        if (preg_match('/%Y|%y|%d|%m/', $realpath)) return $realpath;

        $dir = str_replace(strrchr($realpath, '/'), '', $realpath);
        if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
            static::addLog($realpath . ' : realpath no such direction', BaseLogger::ERROR);
            return false;
        }
        if (!is_file($realpath) && !touch($realpath)) {
            static::addLog($realpath . ' : realpath no such file', BaseLogger::ERROR);
            return false;
        }
        return $realpath;
    }

    public static function readInclude()
    {
        $array = static::readIni();
        isset($array['include']['files']) or static::exit('read [include][files] module failed');
        $filepath = $array['include']['files'];
        $realpath = static::findRealpath($filepath, false);
        $str = strrchr($realpath, '/'); //正则匹配返回类似：/*.ini
        $dir = str_replace($str, '', $realpath);
        is_dir($dir) or static::exit($dir . ' : no such directory');
        $str = str_replace(['/', '.', '*'], ['', '\.', '.*?'], $str);
        $pattern =  '/^' . $str . '$/';
        $files = scandir($dir);
        $databack = [];
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            if (preg_match($pattern, $file)) {
                $filepath = $dir . '/' . $file;
                $fileArray = static::checkConsumerFile($filepath);
                if (empty($fileArray)) continue;
                $databack[] = $fileArray;
            }
        }
        return $databack;
    }

    public static function checkConsumerFile($filepath)
    {
        $array = parse_ini_file($filepath);
        !empty($array['queueName']) or static::exit('invalid value : queueName');
        $array['qos'] = $qos = isset($array['qos']) && intval($array['qos']) > 0 ?  $array['qos'] : 1;
        $array['duplicate'] = $duplicate = isset($array['duplicate']) && intval($array['duplicate']) >= 1 ?  $array['duplicate'] : 1;
        $array['numprocs'] = $numprocs = isset($array['numprocs']) && intval($array['numprocs']) ?  $array['numprocs'] : 1;
        $array['program'] = $program = isset($array['program']) && !empty($array['program']) ?  $array['program'] : uniqid();
        // 校验queueName是否存在，校验是否已经启动消费者
        $amqpApi = static::$amqpApi;
        $queueName = $array['queueName'];
        $stat = static::statProcess();
        for ($i = 0; $i < $duplicate; $i++) {
            $queue = $duplicate == 1 ? $queueName : $queueName . '_' . $i;
            $info = $amqpApi->getBinding($queue);
            if (empty($info)) {
                static::$logger->addLog($queue . ' is not decalre', BaseLogger::WARNING);
                continue;
            }
            $num = isset($stat[$queue]) ? $stat[$queue] : 0;
            $processNum = ($numprocs - $num) > 0 ? ($numprocs - $num) : 0;
            for ($k = 0; $k < $processNum; $k++) {
                // 因为没有将启动消费者的配置项视为对象，从而带来的问题就是修改格式会很麻烦
                // 起初觉得这么做不会在变了，但是做着做着发现还是有很多优化的空间
                // 所有面向对象的思想不能偷懒
                static::$queueArray[] = [$queue, $qos];
            }
        }
        return $array;
    }

    public static function statProcess()
    {
        $array = ProcessHelper::read();
        $stat = array();
        foreach ($array as $v) {
            list($pid, $ppid, $queueName, $qos) = $v;
            isset($stat[$queueName]) ? $stat[$queueName]++ : $stat[$queueName] = 1;
        }
        return $stat;
    }

    public static function readCommon()
    {
        $array = static::readIni();
        isset($array['common']) or static::exit("read [common] module failed");
        $common = $array['common'];
        $pidfile = isset($common['pidfile']) && !empty($common['pidfile']) ? $common['pidfile'] : DEFAULT_PIDFILE_PATH;
        $pidfile = static::findRealpath($pidfile);
        is_file($pidfile) or static::exit($pidfile . ' :pidfile no such file');
        $common['pidfile'] = $pidfile;
        return $common;
    }

    public static function readPpid()
    {
        $common = static::readCommon();
        $pidfile = $common['pidfile'];
        return intval(file_get_contents($pidfile));
    }

    public static function writePpid(int $ppid)
    {
        $common = static::readCommon();
        $pidfile = $common['pidfile'];
        return file_put_contents($pidfile, $ppid);
    }

    public static function checkProcessAlive($pid)
    {
        if (empty($pid)) return false;
        $pidinfo = `ps co pid {$pid} | xargs`;
        $pidinfo = trim($pidinfo);
        $pattern = "/.*?PID.*?(\d+).*?/";
        preg_match($pattern, $pidinfo, $matches);
        return empty($matches) ? false : ($matches[1] == $pid ? true : false);
    }

    public static function readCommun()
    {
        $array = static::readIni();
        $className = isset($array['communication']['class']) ? $array['communication']['class'] : 'pipe';
        array_key_exists($className, self::$communClassMap) or static::exit('invalid value of : ' . $className . ', available value is : ' . implode('|', array_keys(self::$communClassMap)));
        $class = self::$communClassMap[$className];
        isset($array[$className]) or self::exit(' read module [' . $className . '] failed');
        $communArray = isset($array[$className]) ? $array[$className] : array();
        $communArray['class'] = $class;
        static::loggerHelp($communArray);
        return $communArray;
    }

    public static function readIni()
    {
        if (!empty(static::$array)) return static::$array;
        is_file(DEFALUT_AMQPINI_PATH) or static::exit(DEFALUT_AMQPINI_PATH . ' : amqpini no such file');
        static::$array = parse_ini_file(DEFALUT_AMQPINI_PATH, true);
        return static::$array;
    }

    

    public static function getCommand()
    {
        $common = static::readCommon();
        return $common['command'] ?: '/usr/bin/php';
    }

    public static function getUnix()
    {
        $common = static::readCommon();
        return $common['unix'] ?: '/usr/local/var/run/amqp_consumer_serve.sock';
    }

    public static function getPipe()
    {
        $common = static::readCommon();
        $pipe_file = $common['pipe_file'] ?: '/tmp/amqp_pipe';
        @touch($pipe_file) or static::exit($pipe_file . ' : pipe no such file');
        return true;
    }

    public static function getLogger() {
        list($access_log, $error_log, $level) = static::getDefaultLogger();
        return new Logger($access_log, $error_log, $level);
    }

    public static function exit($error)
    {
        static::addLog($error, BaseLogger::ERROR);
        exit($error);
    }

    public static function addLog($msg, $level=BaseLogger::INFO)
    {
        if (!static::$logger)
            static::$logger = new Logger(DEFAULT_ACCESS_LOG, DEFAULT_ERROR_LOG);
        static::$logger->addLog($msg, $level);
    }
}
