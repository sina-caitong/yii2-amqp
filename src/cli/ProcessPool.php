<?php

$flag = 1;

class process

{
    public $pid;

    public $name;

    public $file;

    public $num;
}

class instance
{
    public $processIdx;

    public $proc = [];

    public $processNum;
}

function sigHandler($sigNo)
{
    global $flag;

    $flag = 0;

    echo "信号中断处理" . PHP_EOL;
}

function processPool(instance &$instance, $num)
{
    if (!$instance || $num == 0) {
        fprintf(STDERR, "%s", "参数错误");
        return 1;
    }

    $instance->processIdx = 0;

    $instance->processNum = $num;

    pcntl_signal(SIGINT, 'sigHandler');

    pcntl_signal(SIGTERM, 'sigHandler');

    $process = new process();

    for ($i = 1; $i <= $num; $i++) {

        $instance->proc[$i] = clone $process;

        $instance->proc[$i]->file = $i;

        $instance->proc[$i]->pid = pcntl_fork();

        $instance->processIdx = $i;

        if ($instance->proc[$i]->pid < 0) {

            exit("进程创建失败");
        } else if ($instance->proc[$i]->pid > 0) {

            //nothing

            continue;
        } else {

            worker($instance);
        }
    }

    master($instance);

    $exitProcess = [];

    while (1) {

        for ($i = 1; $i <= $num; $i++) {

            //非阻塞方式回收子进程

            pcntl_waitpid($instance->proc[$i]->pid, $status, WNOHANG);

            if ($status) {

                $exitProcess[] = $instance->proc[$i]->pid;

                fwrite(STDOUT, "worker#" . $instance->proc[$i]->pid . "-" . $status, 30);
            }
        }

        if (count($exitProcess) == $instance->processNum) {

            exit(0);
        }

        usleep(1000);
    }
}

//简单的轮询算法  自己可以用队列，随机，链表，栈链，二叉树啥的折腾

function roundRobin(&$instance, $roll)

{

    /** @var instance $instance */

    return $instance->proc[$roll % $instance->processNum + 1];
}

function master(&$instance)

{

    /** @var instance $instance */

    fprintf(STDOUT, "master 进程 %d\n", $instance->processIdx);

    global $flag;

    $roll = 0;

    while ($flag) {

        pcntl_signal_dispatch();

        /** @var process $process */

        $process = roundRobin($instance, $roll++);

        echo "轮询的进程:" . $process->pid . PHP_EOL;

        $file = $process->file;

        posix_mkfifo($file, 0666);

        $fd = fopen($file, "w");

        fwrite($fd, "hi", 2);

        sleep(1);
    }

    for ($i = 1; $i <= $instance->processNum; $i++) {

        posix_kill($instance->proc[$i]->pid, 9);
    }

    fprintf(STDOUT, "master shutdown %d\n", $instance->processIdx);
}

function getProcess(&$instance)

{

    /** @var instance $instance */

    return $instance->proc[$instance->processIdx];
}

function worker(&$instance)

{

    /** @var process $process */

    $process = getProcess($instance);

    while (1) {

        $file = $process->file;

        posix_mkfifo($file, 0666);

        $fd = fopen($file, "r");

        $content = fread($fd, 10);

        fprintf(STDOUT, "worker#%d读取的内容：%s file=%d\n", posix_getpid(), $content, $file);
    }

    exit(0);
}

$instance = new instance();

processPool($instance, 5);
