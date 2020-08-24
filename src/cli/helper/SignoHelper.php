<?php

namespace pzr\amqp\cli\helper;

class SignoHelper
{

    /** @var 子进程reload */
    const KILL_CHILD_RELOAD = SIGTERM;
    /** @var 子进程stop */
    const KILL_CHILD_STOP = SIGKILL;

    /** @var 自定义事件：通知父进程 */
    const KILL_NOTIFY_PARENT = SIGUSR2;
    /** @var 父进程退出 */
    const KILL_DOMAIN_STOP = SIGHUP;


}