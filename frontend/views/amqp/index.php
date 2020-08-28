<?php

/* @var $this yii\web\View */
/* @var $form yii\bootstrap\ActiveForm */
/* @var $model app\models\LoginForm */

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

$this->title = 'CONSUMER PROCESS MANAGER';
$this->params['breadcrumbs'][] = $this->title;

[$list, $stat, $color] = $model->list();
?>
<div class="" style="text-align: center;">
    <h1><?= Html::encode($this->title) ?></h1>
    <br>
    <div style="margin-left: 10%; text-align:left">
        <button onclick="request('reloadall')">REFRESH</button>
        <button onclick="request('startall')">START ALL</button>
        <button onclick="request('stopall')">STOP ALL</button>

    </div>
    <br>

    <table class="hovertable" style="width: 80%; margin-left:10%;">
        <thead>
            <th>子进程ID</th>
            <th>父进程ID</th>
            <th>队列名称</th>
            <th>文件标志</th>
            <th width="50%">操作</th>
        </thead>
        <?php foreach ($list as $queue => $d) {
            foreach ($d as $k => $v) { ?>
                <tbody>
                    <tr onmouseover="this.style.backgroundColor='#ffff66';" onmouseout="this.style.backgroundColor='#d4e3e5';" style="color: <?= $v['color'] ?>;">
                        <td><?= $v['pid'] ?></td>
                        <td><?= $v['ppid'] ?></td>
                        <td><?= $v['queueName'] ?></td>
                        <td><?= $v['program'] ?></td>
                        <td>
                            <a onclick="request('restart', <?= $v['pid'] ?>, <?= $v['ppid'] ?>)">restart</a> &nbsp;&nbsp;&nbsp;
                            <a onclick="request('stop', <?= $v['pid'] ?>, <?= $v['ppid'] ?>)">stop</a>&nbsp;&nbsp;&nbsp;
                            <a onclick="request('copy', <?= $v['pid'] ?>, <?= $v['ppid'] ?>)">copy</a>
                        </td>
                    </tr>
                <?php } ?>
            <?php } ?>
                </tbody>
    </table>
</div>
<br>
<div style="margin-left:10%">
    <?php foreach ($stat as $queue => $count) { ?>
        <font color="<?= $color[$queue] ?>"><?= $queue ?>: <?= $count ?></font> <br>
    <?php } ?>
</div>

<script>
    // setInterval(function() {
    //     window.location.reload();
    // }, 3000);

    function request(command, pid = 0, ppid = 0) {
        var url = "index.php?r=amqp%2Frequest&command=" + command + "&pid=" + pid + "&ppid=" + ppid;
        $.get(url, function($data, $status) {
            window.location.reload();
        });
    }
</script>


<style>
    th {
        text-align: center;
    }

    table {
        text-align: center;
    }

    table.hovertable {
        font-family: verdana, arial, sans-serif;
        font-size: 11px;
        color: #333333;
        border-width: 1px;
        border-color: #999999;
        border-collapse: collapse;
    }

    table.hovertable th {
        background-color: #c3dde0;
        border-width: 1px;
        padding: 8px;
        border-style: solid;
        border-color: #a9c6c9;
    }

    table.hovertable tr {
        background-color: #d4e3e5;
    }

    table.hovertable td {
        border-width: 1px;
        padding: 8px;
        border-style: solid;
        border-color: #a9c6c9;
    }
</style>