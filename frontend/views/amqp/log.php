<?php

/* @var $this yii\web\View */
/* @var $form yii\bootstrap\ActiveForm */
/* @var $model app\models\LoginForm */

use yii\helpers\Html;
use yii\bootstrap\ActiveForm;

$this->title = 'AMQP LOGGER MANAGER';
$this->params['breadcrumbs'][] = $this->title;
$limit = isset($_GET['limit']) ? $_GET['limit'] : 20;
$data = $model->traceLog($limit);
$level = isset($_GET['level']) ? $_GET['level'] : $data['level'];
$logs = $data['logs'];
?>
<div class="" style="text-align: left;">
    <h1><?= Html::encode($this->title) ?></h1>
    <?php
    foreach ($logs as $log) {
        if (empty($log)) continue;
    ?>
        <p><?= $log ?></p>
        <pre style="height:auto;">
<?= `tail -{$limit} {$log}` ?>
    </pre>
        <br>
    <?php } ?>
</div>


<script>
    setInterval(function() {
        window.location.reload();
    }, 5000);

    function request(command, pid = 0, ppid = 0) {
        var url = "index.php?r=amqp%2Frequest&command=" + command + "&pid=" + pid + "&ppid=" + ppid;
        $.get(url, function($data, $status) {
            window.location.reload();
        });
    }
</script>