<?php

namespace pzr\amqp\cli\communication;

use Pheanstalk\Pheanstalk;

class BeanstalkCommun extends BaseCommun
{
    private $talker = null;
    private $logger = null;

    const PIPE = 'AMQP_PIPE';

    public function __construct(array $config)
    {
        parent::__construct($config);
        $host = $config['host'];
        $port = $config['port'];
        $this->talker = Pheanstalk::create($host, $port);
    }

    public function open()
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read()
    {
        $array = [];
        do {
            $this->talker->watch(self::PIPE);
            $response = $this->talker->statsTube(self::PIPE);
            if (empty($response->current_jobs_ready)) break;
            $job = $this->talker->reserve();
            try {
                $string = $job->getData();
                $array[] = explode(',', $string);
                $this->talker->delete($job);
            } catch (\Exception $e) {
                $this->talker->release($job);
            }
        } while(true);
        $this->logger->addLog(sprintf("[talker] read buffer: %s", json_encode($array)));
        return $array;


            
    }

    public function write(string $queueName, string $program)
    {
        if (empty($queueName) || empty($program)) return false;
        $string = $queueName . ',' . $program;
        $this->talker->useTube(self::PIPE)->put($string);
        $this->logger->addLog(sprintf("[talker] write:%s", $string));
    }

    public function write_batch(array $array)
    {
        $strings = [];
        foreach ($array as $a) {
            if (empty($a['queueName']) || empty($a['program'])) continue;
            $string = $a['queueName'] . ',' . $a['program'];
            $this->talker->useTube(self::PIPE)->put($string);
            $strings[] = $string;
        }
        $this->logger->addLog(sprintf("[talker] write:%s", implode('|', $strings)));
    }

    public function flush()
    {
        
    }
}
