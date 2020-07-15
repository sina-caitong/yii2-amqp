<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;
use app\models\Job;
use app\models\RequestJob;
use pzr\amqp\AmqpBase;
use pzr\amqp\RpcAmqp;
use yii\queue\cli\Queue;
use yii\queue\LogBehavior;

class SiteController extends Controller
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

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex()
    {
        return $this->render('index');
        // Yii::$app->delayQueue->off(AmqpBase::EVENT_BEFORE_PUSH);
        // Yii::$app->delayQueue->off(AmqpBase::EVENT_AFTER_PUSH);

        // Yii::$app->delayQueue->on(AmqpBase::EVENT_PUSH_ACK, function ($event) {
        //     error_log('job ack:' . $event->job . PHP_EOL, 3, '/users/pzr/test.log');
        //     $event->handled = true;
        // });

        // Yii::$app->delayQueue->on(AmqpBase::EVENT_PUSH_NACK, function ($event) {
        //     error_log('job nack:' . $event->job, 3, '/users/pzr/test.log');
        //     $event->handled = true;
        // });


        // Yii::$app->delayQueue->on(AmqpBase::EVENT_BEFORE_PUSH, function ($event) {
        //     Yii::$app->delayQueue->setPriority(10)->bind();
        // });
//  set_time_limit(0);
        // Yii::$app->easyQueue->on(AmqpBase::EVENT_BEFORE_PUSH, function ($event) {
            // Yii::$app->easyQueue->bind();
            // $event->noWait = true;
        // });

        // Yii::$app->easyQueue->bind(); die;
        // Yii::$app->amqpApi->setPolicy();
        // die;


        // Yii::$app->delayQueue->on(AmqpBase::EVENT_AFTER_PUSH, function ($event) {
        //     error_log('after push site'. PHP_EOL, 3, '/users/pzr/test.log');
        //     $event->handled = true;
        // });


        // $stime = microtime(true);
        // for ($i = 1; $i <= 10000; $i++) {
        //     Yii::$app->easyQueue->push(new Job([
        //         'file' => 'test',
        //         'url' => 'image',
        //         'priority' => 0,
        //     ]));
        // }
        

        // $arr = [];
       
        // $stime = microtime(true);
        // for ($i = 1; $i <= 40000; $i++) {
        //     $arr[] = new Job();
        //     if ($i % 100 == 0) {
        //         Yii::$app->easyQueue->myPublishBatch($arr);
        //         $arr = [];
        //     }
        // }
        // $etime=microtime(true);//获取程序执行结束的时间  
        // echo "{$stime}, {$etime} \n";
        // $total=$etime-$stime;   //计算差值  
        // $str_total = var_export($total, TRUE);
        // if (substr_count($str_total, "E")) {
        //     $float_total = floatval(substr($str_total, 5));
        //     $total = $float_total / 100000;
        //     echo "$total" . '秒';
        // } else {
        //     echo ($total) . '微秒';
        // }

        // Yii::$app->easyQueue->myPublishBatch($arr);

    }

    public function actionRpc() {
        Yii::$app->rpcQueue->on(RpcAmqp::EVENT_BEFORE_PUSH, function($event){
            Yii::$app->rpcQueue->bind();
        });
        
        for($i=0; $i<100; $i++) {
            $jobs[] = new RequestJob([
                'request' => 'req_'. $i,
            ]);
        }

        // var_dump($jobs); die;
        $this->log('rpc start');
        // $response = Yii::$app->rpcQueue->publish($job);
        $response = Yii::$app->rpcQueue->setQos(10)->myPublishBatch($jobs);
        var_dump($response); die;
    }

    public function log($msg) {
        @error_log($msg . PHP_EOL, 3, '/Users/pzr/test.log');
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
            return $this->goBack();
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

        return $this->goHome();
    }

    /**
     * Displays contact page.
     *
     * @return Response|string
     */
    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');

            return $this->refresh();
        }
        return $this->render('contact', [
            'model' => $model,
        ]);
    }

    /**
     * Displays about page.
     *
     * @return string
     */
    public function actionAbout()
    {
        return $this->render('about');
    }
}
