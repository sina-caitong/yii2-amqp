<?php

namespace pzr\amqp\cli\Communication;

use Monolog\Logger as BaseLogger;

class PipeCommun extends BaseCommun
{
    private $fp = null;
    private $pipe_file;

    public function __construct(array $config)
    {
        parent::__construct($config);
        $this->pipe_file = $config['pipe_file'];
    }

    public function open()
    {
        if (is_resource($this->fp)) return true;
        is_file($this->pipe_file) ?: @touch($this->pipe_file);
        return $this->fp = fopen($this->pipe_file, 'r+');
    }

    public function close()
    {
        if (is_resource($this->fp))
            fclose($this->fp);
    }

    public function read()
    {
        $size = @filesize($this->pipe_file);
        if (empty($size)) return false;
        $this->open();
        $buffer = @fread($this->fp, $size);
        $this->close();
        $this->flush();
        $this->logger->addLog(sprintf("[pipe] read buffer: %s", $buffer));
        $array = explode('|', $buffer);
        $data = array();
        foreach ($array as $k => $v) {
            if (empty($v)) continue;
            $arr = explode(',', $v);
            if (empty($arr)) continue;
            $data[] = $arr;
        }
        return $data;
    }

    public function write(string $queueName, string $program)
    {
        if (empty($queueName) || empty($program)) return false;
        $this->open();
        $string = '|' . $queueName . ',' . $program;
        $len = strlen($string);
        $size = fwrite($this->fp, $string, $len);
        $level = $len == $size ? BaseLogger::INFO : BaseLogger::WARNING;
        $this->logger->addLog(sprintf("[pipe] write:%s, len:%d, succ:%d", $string, $len, $size), $level);
        return $size;
    }

    public function write_batch(array $array)
    {
        $strings = [];
        foreach ($array as $a) {
            if (empty($a['queueName']) || empty($a['program'])) continue;
            $strings[] = $a['queueName'] . ',' . $a['program'];
        }
        if (empty($strings)) return false;
        $this->open();
        $string = implode('|', $strings);
        $len = strlen($string);
        $size = fwrite($this->fp, $string, $len);
        $level = $len == $size ? BaseLogger::INFO : BaseLogger::ERROR;
        $this->logger->addLog(sprintf("[pipe] write:%s, len:%d, succ:%d", $string, $len, $size), $level);
        return $size;
    }

    public function flush()
    {
        return unlink($this->pipe_file);
    }
}
