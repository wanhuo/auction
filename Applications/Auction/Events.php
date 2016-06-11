<?php
/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

use \GatewayWorker\Lib\Gateway;

use \Workerman\Lib\BaseJson;
use \Workerman\Service\UserService;
use \Workerman\Lib\TomConst;
use \Workerman\Connection\AsyncTcpConnection;
use \MongoQB\Builder;

class Events
{
    public static function onMessage($client_id, $message)
    {
        $login_sessions = TomConst::$const['USER_LOGIN_SESSION'];
        $redis = new Predis\Client();
        $mongo = new Builder();

        $user_service = new UserService();
        // 客户端传递的是json数据
        $json_arr = BaseJson::decode($message);
        if(!$json_arr || ! isset($json_arr['type']))
        {
            return null;
        }

        //没登录
        if(!isset($_SESSION['uid'])) {
            if(isset($json_arr['session_id']) && $json_arr['type'] == 'CONN_LOGIN'){
                $clients_key = sprintf(TomConst::$const['USER_CLIENT_IDS'],$json_arr['session_id']);

                echo 'CONN_LOGIN';
                global $connection_count;

                $session_id = $json_arr['session_id'];
                $user = $user_service->get_user($session_id);
                if(!$user){
                    echo '重新登录';
                    self::_login($user_service, $redis, $json_arr, $client_id, $login_sessions);
                    return;
                }
                //已经有连接
                $clients = $redis->smembers($clients_key);echo count($clients).PHP_EOL;
                if(count($clients)>=1){
                    //重复打开客户端,关闭
                    $type = 'REPEAT_LOGIN';
                    $json_data = ['msg'=>TomConst::$const[$type],'type'=>$type];
                    Gateway::sendToAll(BaseJson::encode($json_data),$clients);
                    return;
                }else{
                    $redis->sadd($clients_key,$client_id);
                }

                $type = 'IS_LOGIN';
                if($redis->sismember($login_sessions, $session_id)){
                    echo 'dccc';
                    $user = $user_service->get_user($session_id);
                    //删除退出的定时器
                    $worker = new AsyncTcpConnection('text://0.0.0.0:6868');
                    $worker->send(BaseJson::encode(['client_id'=>$client_id,'type'=>'del','uid'=>$user['uid']]));
                    $worker->connect();

                    $_SESSION['uname'] = $user['name'];
                    $_SESSION['uid'] = $user['uid'];
                    $_SESSION['session_id'] = $session_id;
                }else{
                    ++$connection_count;
                    $redis->sadd($login_sessions, $json_arr['session_id']);
                }
                $json_data = ['msg'=>$user['name'],'type'=>$type,'total_conn_person'=>$connection_count];
                Gateway::sendToClient($client_id,BaseJson::encode($json_data));
                return null;
            }else{
                var_dump($json_arr);
                $clients_key = sprintf(TomConst::$const['USER_CLIENT_IDS'],$_SESSION['session_id']);

                if($json_arr['type'] == 'DO_LOGIN'){
                    $clients = $redis->smembers($clients_key);
                    if(count($clients)>1){echo 333;
                        //重复打开客户端,关闭
                        $type = 'REPEAT_LOGIN';
                        $json_data = ['msg'=>TomConst::$const[$type],'type'=>$type];
                        Gateway::sendToClient($client_id,BaseJson::encode($json_data));
                        $redis->sadd($clients_key,$client_id);
                        return;
                    }

                    self::_login($user_service, $redis, $json_arr, $client_id, $login_sessions);
                }else{
                    $type = 'TYPE_ERR';
                    $data = ['msg'=>TomConst::$const[$type],'type'=>$type];
                    Gateway::sendToClient($client_id,BaseJson::encode($data));
                    return null;
                }
            }
        }else{
            var_dump($_SESSION);
            if($json_arr['type'] == 'DO_LOGIN'){
                if(isset($_SESSION['uid'])){
                    if($redis->sismember($login_sessions, $_SESSION['session_id'])){

                        $user = $user_service->get_user($_SESSION['session_id']);
                        //删除退出的定时器
                        $worker = new AsyncTcpConnection('text://0.0.0.0:6868');
                        $worker->send(BaseJson::encode(['client_id'=>$client_id,'type'=>'del','uid'=>$user['uid']]));
                        $worker->connect();

                        $type = 'IS_LOGIN';
                        $json_data = ['msg'=>$user['name'],'type'=>$type];
                        Gateway::sendToClient($client_id,BaseJson::encode($json_data));
                        return null;
                    }else{
                        $redis->sadd($login_sessions, $json_arr['session_id']);
                    }
                }
            }

            // 根据类型执行不同的业务
            switch($json_arr['type'])
            {
                // 客户端回应服务端的心跳
                case 'pong':
                    break;

                case 'SEND':
                    $content = $json_arr['content'];
                    $time = date('Y-m-d H:i:s');
                    $json = ['content'=>$content,'time'=>$time,'type'=>'SHOW_MSG','date'=>date('Y-m-d')];
                    $mongo->where(['pid'=>'1'])
                        ->push('chat_info',$json)->update('product');
                    Gateway::sendToAll(BaseJson::encode($json));
                    break;

                case 'DO_LOGOUT':
                    $user_service->logout($redis, $client_id,$_SESSION['uid']);
                    unset($_SESSION['uname']);
                    unset($_SESSION['uid']);
                    unset($_SESSION['session_id']);
                    break;

                case 'BID_ADD':
                    $auction_service = new \Workerman\Service\AuctionService();
                    $result = $auction_service->bid(['uid'=>$_SESSION['uid'],'price'=>$json_arr['price'],'pid'=>'1']);
                    Gateway::sendToAll(BaseJson::encode($result));
                    if($result['result'] == 'ok'){
                        Gateway::sendToClient($client_id,json_encode(['msg'=>'您已经出价成功','type'=>'BID','result'=>'ok2']));
                    }
                    break;

            }
        }
   }
   
   /**
    * 当客户端断开连接时
    * @param integer $client_id 客户端id
    */

    public static function onClose($client_id){
        $redis = new Predis\Client();
        //判断有登录 在做清除登录信息操作
        if(isset($_SESSION['uid'])){
            //已经有连接
            $redis->del(sprintf('user_%s_client',$_SESSION['uid']));

            $user_service = new UserService();
            $user = $user_service->get_user($_SESSION['uid']);
            $redis->srem(sprintf(TomConst::$const['USER_CLIENT_IDS'],$user['session_id']),$client_id);

            global $connection_count;
            $connection_count--;
            Gateway::sendToAll(BaseJson::encode(['total_conn_person'=>$connection_count,'a'=>1]));
            $worker = new AsyncTcpConnection('text://0.0.0.0:6868');
            $worker->send(BaseJson::encode(['type'=>'add','client_id'=>$client_id,'uid'=>$_SESSION['uid']]));
            $worker->connect();
        }
    }


//    public static function onConnect($client_id){
//
//        global $connection_count;
//        $redis = new MyRedis();
//        $user_service = new UserService();
//
//        //没有登录信息 先登录
//        if(!$_SESSION['uid']){
//            $type = 'DO_LOGIN';
//            $data = ['msg'=>TomConst::$const[$type],'type'=>$type,
//                'total_conn_person'=>is_null($connection_count)?0:$connection_count];
//            Gateway::sendToClient($client_id,BaseJson::encode($data));
//            return;
//        }else{
//
//            $key1 = sprintf(TomConst::$const['USER_CLIENT_IDS'],$_SESSION['session_id']);
//            $clients = $redis->smembers($key1);
//
//            //删除退出的定时器
//            $worker = new AsyncTcpConnection('text://0.0.0.0:6868');
//            $worker->send(BaseJson::encode(['client_id'=>$client_id,'type'=>'del','session_id'=>$_SESSION['session_id']]));
//            $worker->connect();
//
//            foreach($clients as $cid){
//                $redis->sRem($key1,$cid);
//                $type = 'REPEAT_LOGIN';
//                $data = ['type'=>$type,'msg'=>TomConst::$const[$type]];
//                Gateway::sendToClient($client_id,BaseJson::encode($data));
//            }
//            $redis->sadd($key1, $client_id);
//            ++$connection_count;
//        }
//
//        $user = $user_service->get_user($_SESSION['uid']);

//        $connection_count = count($redis->smembers(TomConst::$const['USER_LOGIN_SESSION']));
//        $data = ['total_conn_person'=>$connection_count];
//        Gateway::sendToAll(BaseJson::encode($data));
//    }

    /***
     * @param $user_service UserService
     * @param $redis Predis\Client
     * @param $json_arr
     * @param $client_id
     * @param $login_sessions
     * @return null
     */
    private static function _login($user_service,$redis,$json_arr,$client_id,$login_sessions){
        if(($user = $user_service->CheckPwd($json_arr)) == false){
            $type = 'ERR_LOGIN';
            Gateway::sendToClient($client_id, BaseJson::encode(['msg'=>TomConst::$const[$type],'type'=>$type]));
            return null;
        }else{
            echo 'do.....';
            global $connection_count;
            $connection_count++;

            $type = 'IS_LOGIN';
            $user['session_id'] = $json_arr['session_id'];
            $user['client_id'] = $client_id;

            $user_service->set_user($user);
            $_SESSION['uname'] = $user['name'];
            $_SESSION['uid'] = $user['id'];
            $_SESSION['session_id'] = $json_arr['session_id'];

            $redis->sadd($login_sessions,$json_arr['session_id']);
//            $redis->set($json_arr['session_id'],$user['id']);

            $clients_key = sprintf(TomConst::$const['USER_CLIENT_IDS'],$json_arr['session_id']);
            //已经有连接
            $redis->sadd($clients_key,$client_id);

            var_dump($redis->smembers($clients_key));
            $json_data = ['msg'=>$json_arr['name'],'type'=>$type,'total_conn_person'=>$connection_count];
            Gateway::sendToClient($client_id,BaseJson::encode($json_data));
            return null;
        }
    }
}
