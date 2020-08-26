<?php

/* @var $this yii\web\View */
/* @var $form yii\bootstrap\ActiveForm */
/* @var $model app\models\LoginForm */

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

$this->title = 'AMQP CONSUMER TAG MANAGER';
$this->params['breadcrumbs'][] = $this->title;

$stat = $model->statV2();
?>
<div class="" style="text-align: center;">
    <h1><?= Html::encode($this->title) ?></h1>



    <?php foreach ($stat as $queue => $d) { ?>
        <table class="hovertable" style="width: 80%; margin-left:10%;">
            <thead>
                <th>队列名称</th>
                <th>实际消费者</th>
                <th>文件消费者</th>
                <th width="50%">操作</th>
            </thead>
            <tbody>
                <tr>
                    <td><?= $queue ?></td>
                    <td><?= $d['nums'] ?></td>
                    <td><?= $d['processNums'] ?></td>
                    <td>
                        <a onclick="delAll('<?= $queue ?>')">删除全部</a>
                    </td>
                </tr>
                <thead>
                    <th>AMQP连接</th>
                    <th>QOS</th>
                    <th>ACK</th>
                    <th></th>
                </thead>
            </tbody>
            <?php foreach ($d['connections'] as $k => $v) { ?>
                <tr onmouseover="this.style.backgroundColor='#ffff66';" onmouseout="this.style.backgroundColor='#d4e3e5';">
                    <td><?= $v['connection'] ?></td>
                    <td><?= $v['qos'] ?></td>
                    <td><?= $v['ack'] ?></td>
                    <td> <a onclick="delOne('<?= $v['connection'] ?>', '<?= $queue ?>')">删除</a> </td>
                </tr>
            <?php } ?>
        </table>
        <hr>
    <?php } ?>

</div>
<br>

<script>
    function request(command, pid = 0, ppid = 0) {
        var url = "index.php?r=amqp%2Frequest&command=" + command + "&pid=" + pid + "&ppid=" + ppid;
        $.get(url, function($data, $status) {
            window.location.reload();
        });
    }

    function delAll(queue) {
        var url = "index.php?r=amqp%2Fdelbyqueue&queue=" + queue;
        $.get(url, function(data, status) {
            window.location.reload();
        });
    }

    function delOne(conn, queue) {
        var url = "index.php?r=amqp%2Fdelbyconn&conn=" + conn + "&queue=" + queue;
        $.get(url, function(data, status) {
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