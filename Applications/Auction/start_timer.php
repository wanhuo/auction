<?php
/**
 * Created by PhpStorm.
 * User: yaoheng
 * Date: 16/5/26
 * Time: 下午9:41
 */

//定时器类===由于系统是多进程的,A进程生成的定时器在B进程中无法删除,单独开一个进程监控定时任务

use \Workerman\Worker;
use \Workerman\Lib\BaseJson;
use \Workerman\Lib\Timer;

// 自动加载类
require_once __DIR__ . '/../../Workerman/Autoloader.php';

$worker = new Worker("text://0.0.0.0:6868");
$worker->name = 'tom_timer';
$worker->count = 1;


$worker->onMessage = function($conn, $data){
    if(!$data){
        return null;
    }
    $result = BaseJson::decode($data);
    $user_service = new \Workerman\Service\UserService();
    $redis = new Predis\Client();
    $user = $redis->hgetall(sprintf(\Workerman\Lib\TomConst::$const['USER_INFO_UID'],$result['uid']));
    if(!$user){
        echo $data.PHP_EOL;
        echo '空的回调user'.PHP_EOL;
        return;
    }
    $key = sprintf(\Workerman\Lib\TomConst::$const['USER_ALARM'],$user['session_id']);
    if($result['type'] == 'add'){
//        echo '添加定时器';
        $timer_id = Timer::add(10, [$user_service,'logout'] ,[$redis,$result['client_id'],$user['session_id']] ,false);
        //保存进redis中 如果30分钟之内登录  则删除定时器
        $redis->sadd($key, $timer_id);
        $redis->expire($key,10);
    }elseif($result['type'] == 'del'){
//        echo '删除定时器';
        $timer_ids = $redis->smembers($key);
        foreach($timer_ids as $id){
            Timer::del($id);
        }
    }
};


// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}
