<?php

namespace pzr\amqp\cli\handler;

interface HandlerInterface
{
    /** 消息队列：增加一条新增的消费者消息 */
    const EVENT_ADD_QUEUE = 'add_queue';
    /** 消息队列：杀死一个消费者的PID */
    const EVENT_DELETE_PID = 'delete_pid';
    /** 消息队列：杀死守护进程的PPID */
    const EVENT_DELETE_PPID = 'delete_ppid';
    /** 进程文件管理的消息队列 */
    const QUEUE = 'process_file_manager';

    /** 消息队列：处理增加一条新增的消费者消息 */
    public function addQueue(int $pid, int $ppid, string $queue, int $qos);

    /** 消息队列：处理杀死子进程PID || 父进程PPID */
    public function delPid(int $pid=0, int $ppid=0);

    /** 读取消息队列的消息并且同步进程文件 */
    public function handle();

    

    
}
