<?php
/**
 * Created by PhpStorm.
 * User: yaoheng
 * Date: 16/5/21
 * Time: 下午1:40
 */

use \Workerman\Worker;
use \Workerman\Lib\Timer;
use \Workerman\Autoloader;
use \Workerman\Lib\MyRedis;
use \Workerman\Lib\MyMongo;
use \Workerman\Lib\BaseJson;
use \Workerman\Service\UserService;

require __DIR__ . '/tom_const.php';

// 自动加载类
require_once __DIR__ . '/../Autoloader.php';
Autoloader::setRootPath(__DIR__);

$worker = new Worker("websocket://".REGISTER_IP.":8686");
$worker->name = 'second_kill';
$worker->count = 1;

$worker->uidConnections = array();

$mongo = new MyMongo();
$mongo->selectDb("wow");

$user_service = new UserService();

$redis = new MyRedis();


const USER_LOGIN_SESSION = 'user_login_session';//存放登录用户
const USER_LOGIN_LIST = 'user_login_list';//存放在线用户 用于统计人数

$worker->onConnect = function($conn) use ($worker,$mongo,$redis)
{
    global $connection_count;
    $key = sprintf('user_alarm_%s',session_id());
    $timer_ids = $redis->smembers($key);
    foreach($timer_ids as $ids){
        $a = Timer::del($ids);var_dump($a);
    }
var_dump($worker);
    //没有登录信息 先登录
    if($redis->sIsMember(USER_LOGIN_SESSION,session_id())==false){
        $type = 'DO_LOGIN';
        $data = ['msg'=>tom_const::$config[$type],'type'=>$type,
            'total_conn_person'=>is_null($connection_count)?0:$connection_count];
        $conn->send(BaseJson::encode($data));
        return;
    }else{
        if($redis->sIsMember(USER_LOGIN_LIST,session_id()) == false){
            $redis->sadd(USER_LOGIN_LIST, session_id());
            // 有新的客户端连接时，连接数+1
            ++$connection_count;
        }
    }


    $data = ['type'=>'CONNECTION_NUM','total_conn_person'=>$connection_count,'c'=>11];
    foreach($worker->connections as $connection)
    {
        $connection->send(BaseJson::encode($data));
    }
};

//退出的时候 清除key
$worker->onClose = function($connection) use ($worker,$mongo,$redis,$user_service)
{
    if($redis->sIsMember(USER_LOGIN_SESSION,session_id())){
        //信息保存30分钟 超时将清除,已避免刷新页面重复登录请求
        $timer_id1 = Timer::add(10, [$user_service,'clean'] ,[$redis,$connection,$worker] ,false);

        $timer_id2 = Timer::add(10, function() use ($worker){
            // 客户端关闭时，连接数-1
            global $connection_count;
            $connection_count--;
            foreach($worker->connections as $connection)
            {
                $connection->send(BaseJson::encode(['type'=>'CONNECTION_NUM','total_conn_person'=>$connection_count] ));
            }
        },null,false);

        //保存进redis中 如果30分钟之内登录  则删除定时器
        $key = sprintf('user_alarm_%s',session_id());
        $redis->sadd($key, $timer_id1);
        $redis->sadd($key, $timer_id2);
        $redis->setTimeout($key,10);
    }
};


$worker->onMessage = function($conn,$data) use ($worker,$mongo,$user_service,$redis){
    if(!$data)
    {
        return;
    }

    global $connection_count;
    $json_arr = BaseJson::decode($data);
    if(!isset($json_arr['type']) || array_key_exists($json_arr['type'], tom_const::$config) == false){
        $conn->send(BaseJson::encode(['msg'=>'发送数据异常,请重新连接','type'=>'TYPE_ERR']));
        return;
    }

    //没登录
    if($redis->sIsMember(USER_LOGIN_SESSION, session_id())==false) {
        if($json_arr['type'] == 'DO_LOGIN'){
            unset($json_arr['type']);
            if($user = $user_service->CheckPwd($json_arr) == false){
                $type = 'ERR_LOGIN';
                $conn->send(BaseJson::encode(['msg'=>tom_const::$config[$type],'type'=>$type]));
                return;
            }else{
                ++$connection_count;
                $type = 'IS_LOGIN';
                $redis_key = sprintf('user_%s',$user['id']);
                $redis->hset($redis_key,'name' ,$json_arr['name']);
                $redis->sadd(USER_LOGIN_SESSION, session_id());
                $redis->sadd(USER_LOGIN_LIST, session_id());
                $conn->uid = $redis_key;
                $worker->uidConnections[$conn->uid] = $conn;
                $json_data = ['msg'=>tom_const::$config[$type],'type'=>$type,'total_conn_person'=>$connection_count];
                $conn->send(BaseJson::encode($json_data));
                return;
            }
        }else{

            $type = 'DO_LOGIN';
            if($json_arr['type'] == $type){
                $conn->send(BaseJson::encode(['msg'=>tom_const::$config[$type],'type'=>$type]));
                return;
            }

        }
    }else{
        foreach($worker->connections as $connection){
            switch($json_arr['type']){
                case 'PONG':
                case 'SEND':
                    $content = $json_arr['content'];
                    $time = date('Y-m-d H:i:s');
                    $json = ['content'=>$content,'time'=>$time,'type'=>'SHOW_MSG','date'=>date('Y-m-d')];
                    $connection->send(BaseJson::encode($json));
                    break;
                case 'BUY':


            }
        }
    }
};


// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}