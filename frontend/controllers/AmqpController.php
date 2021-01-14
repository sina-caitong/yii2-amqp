<?php

namespace app\controllers;

use app\models\AmqpForm;
use app\models\LoginForm;
use Monolog\Logger;
use pzr\amqp\Amqp;
use pzr\amqp\api\AmqpApi;
use yii\web\Controller;
use pzr\amqp\cli\Client;
use pzr\amqp\cli\helper\AmqpIniHelper;
use Yii;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;

class AmqpController extends Controller
{

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    public function actionRequest($command, $pid = 0, $ppid = 0)
    {
        $flag = $this->auth();
        // 接收到请求命令之后，向AMQP服务端发送命令
        $client = new Client();
        $client->request($command . '|' . $pid, $_SESSION['username'], $_SERVER['REMOTE_ADDR']);
        exit('OK');
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        $this->auth();
        $model = new AmqpForm();
        // echo '<pre>';
        // print_r($_SERVER); die;
        return $this->render('/amqp/index', [
            'model' => $model
        ]);
    }

    public function actionStat()
    {
        $this->auth();
        $model = new AmqpForm();
        return $this->render('/amqp/stat', [
            'model' => $model
        ]);
    }

    public function actionDelbyqueue($queue)
    {
        if (empty($queue)) exit(1);
        $logger = AmqpIniHelper::getLogger();
        $config = AmqpIniHelper::readAmqp();
        $api = new AmqpApi($config);
        $info = $api->getInfosByQueue($queue);
        $isDeclare = isset($info['consumer_details']) ? true : false;
        if (!$isDeclare) exit(1);
        $detail = $info['consumer_details'];
        foreach($detail as $d) {
            $conn = $d['channel_details']['connection_name'];
            $logger->addLog(
                sprintf("DELETE [%s] CONNECTION: %s", $queue, $conn),
                Logger::WARNING
            );
            $api->closeConnection($conn);
        }
        exit(0);
    }

    public function actionDelbyconn($conn, $queue)
    {
        if (empty($conn) || empty($queue)) exit(1);
        $logger = AmqpIniHelper::getLogger();
        $logger->addLog(
            sprintf("DELETE [%s] CONNECTION: %s", $queue, $conn),
            Logger::WARNING
        );
        $config = AmqpIniHelper::readAmqp();
        $api = new AmqpApi($config);
        $conn = urldecode($conn);
        $api->closeConnection($conn);
        exit(0);
    }

    public function actionLog()
    {
        $this->auth();
        $model = new AmqpForm();
        return $this->render('/amqp/log', [
            'model' => $model
        ]);
    }

    /**
     * Login action.
     *
     * @return Response|string
     */
    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goHome();
        }

        $model->password = '';
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    /**
     * Logout action.
     *
     * @return Response
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();
        unset($_SESSION['username']);
        return $this->goHome();
    }

    protected function auth() {
        if (!isset($_SESSION['username'])) {
            $this->redirect('index.php?r=amqp/logout');
            return false;
        } else {
            return true;
        }
    }

    public function actionIni() {
        $model = new AmqpForm();
        return $this->render('ini', [
            'model' => $model,
        ]);
    }
}
