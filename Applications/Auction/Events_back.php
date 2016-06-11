<?php
/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

use \GatewayWorker\Lib\Gateway;
use \Workerman\Lib\Timer;
use \Workerman\Lib\MyRedis;
use \Workerman\Lib\MyMongo;
use \Workerman\Lib\BaseJson;
use \Workerman\Service\UserService;
use \Workerman\Lib\TomConst;
use \Workerman\Connection\AsyncTcpConnection;

class Events
{
    private $_product;
    function __construct(){
        $redis = new MyRedis();
        $this->_product = $redis->hgetall('product:1');
        var_dump($this->_product);
    }
//    const USER_LOGIN_SESSION = 'user_login_session';//存放登录用户
//    const USER_LOGIN_LIST = 'user_login_list';//存放在线用户 用于统计人数
//    const USER_CLIENT_IDS = 'user_clients_%s';//key 根据用户id 得到当前用户打开了多少连接 保证只运行1个连接
//    const USER_ALARM = 'user_alarm_%s';


    public static function onMessage($client_id, $message)
    {
        $redis = new MyRedis();
        $user_service = new UserService();
        // debug
//        echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id session:".json_encode($_SESSION)." onMessage:".$message."\n";
        
        // 客户端传递的是json数据
        $json_arr = BaseJson::decode($message);
        if(!$json_arr)
        {
            return null;
        }

        $client_id_key = sprintf(TomConst::$const['USER_CLIENT_IDS'],session_id());
        //没登录
        if($redis->sIsMember(TomConst::$const['USER_LOGIN_SESSION'], session_id())==false) {
            echo 'no_login';
            if(!isset($json_arr['type']) || in_array($json_arr['type'],TomConst::$const )){
                $type = 'TYPE_ERR';
                $data = ['msg'=>TomConst::$const[$type],'type'=>$type];
                Gateway::sendToClient($client_id,BaseJson::encode($data));
                return null;
            }

            if($json_arr['type'] == 'DO_LOGIN'){
                if(($user = $user_service->CheckPwd($json_arr)) == false){
                    $type = 'ERR_LOGIN';
                    Gateway::sendToClient($client_id, BaseJson::encode(['msg'=>TomConst::$const[$type],'type'=>$type]));
                    return null;
                }else{
                    global $connection_count;
                    $connection_count++;

                    $type = 'IS_LOGIN';
                    $user_service->set_user($user);
                    Gateway::setSession($client_id, $user);
//                    $redis->sadd(TomConst::$const['USER_LOGIN_SESSION'], session_id());
                    
                    //登录成功存在client_id
                    $redis->sadd($client_id_key, $client_id);
                    $json_data = ['msg'=>$json_arr['name'],'type'=>$type,'total_conn_person'=>$connection_count];
                    Gateway::sendToAll(BaseJson::encode($json_data));
                    return null;
                }
            }else{
                $type = 'DO_LOGIN';
                Gateway::sendToClient($client_id, BaseJson::encode(['msg'=>TomConst::$const[$type],'type'=>$type]));
                return null;
            }
        }else{
            echo 'is_login';
            if($redis->sIsMember($client_id_key,$client_id) == false){
                $type = 'REPEAT_LOGIN';
                $data = ['type'=>$type,'msg'=>TomConst::$const[$type]];
                Gateway::sendToClient($client_id,BaseJson::encode($data));
                return null;
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
                Gateway::sendToAll(BaseJson::encode($json));
                break;
            
            case 'DO_LOGOUT':
                $user_service->logout($redis, $client_id);
                break;
            
            case 'BID_ADD':
                $auction_service = new \Workerman\Service\AuctionService();
                $result = $auction_service->bid(['uid'=>2,'price'=>140,'pid'=>'1']);
                $redis->hset('product:1','current_price' , $json_arr['price']);
                Gateway::sendToClient($client_id, $result);
                break;

            case 'DO_LOGIN':

            // 客户端登录 message格式: {type:login, name:xx, room_id:1} ，添加到客户端，广播给所有客户端xx进入聊天室
//            case 'login':
//                // 判断是否有房间号
//                if(!isset($message_data['room_id']))
//                {
//                    throw new \Exception("\$message_data['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']} \$message:$message");
//                }


//                // 把房间号昵称放到session中
//                $room_id = $message_data['room_id'];
//                $client_name = htmlspecialchars($message_data['client_name']);
//                $_SESSION['room_id'] = $room_id;
//                $_SESSION['client_name'] = $client_name;
//
//                // 获取房间内所有用户列表
//                $clients_list = Gateway::getClientInfoByGroup($room_id);
//                foreach($clients_list as $tmp_client_id=>$item)
//                {
//                    $clients_list[$tmp_client_id] = $item['client_name'];
//                }
//                $clients_list[$client_id] = $client_name;
//
//                // 转播给当前房间的所有客户端，xx进入聊天室 message {type:login, client_id:xx, name:xx}
//                $new_message = array('type'=>$message_data['type'], 'client_id'=>$client_id, 'client_name'=>htmlspecialchars($client_name), 'time'=>date('Y-m-d H:i:s'));
//                Gateway::sendToGroup($room_id, json_encode($new_message));
//                Gateway::joinGroup($client_id, $room_id);
//
//                // 给当前用户发送用户列表
//                $new_message['client_list'] = $clients_list;
//                Gateway::sendToCurrentClient(json_encode($new_message));

                
            // 客户端发言 message: {type:say, to_client_id:xx, content:xx}
//            case 'say':
//                // 非法请求
//                if(!isset($_SESSION['room_id']))
//                {
//                    throw new \Exception("\$_SESSION['room_id'] not set. client_ip:{$_SERVER['REMOTE_ADDR']}");
//                }
//                $room_id = $_SESSION['room_id'];
//                $client_name = $_SESSION['client_name'];
//
//                // 私聊
//                if($message_data['to_client_id'] != 'all')
//                {
//                    $new_message = array(
//                        'type'=>'say',
//                        'from_client_id'=>$client_id,
//                        'from_client_name' =>$client_name,
//                        'to_client_id'=>$message_data['to_client_id'],
//                        'content'=>"<b>对你说: </b>".nl2br(htmlspecialchars($message_data['content'])),
//                        'time'=>date('Y-m-d H:i:s'),
//                    );
//
//                    Gateway::sendToClient($message_data['to_client_id'], json_encode($new_message));
//                    $new_message['content'] = "<b>你对".htmlspecialchars($message_data['to_client_name'])."说: </b>".nl2br(htmlspecialchars($message_data['content']));
//                    return Gateway::sendToCurrentClient(json_encode($new_message));
//                }
//
//                $new_message = array(
//                    'type'=>'say',
//                    'from_client_id'=>$client_id,
//                    'from_client_name' =>$client_name,
//                    'to_client_id'=>'all',
//                    'content'=>nl2br(htmlspecialchars($message_data['content'])),
//                    'time'=>date('Y-m-d H:i:s'),
//                );
//
//                Gateway::sendToGroup($room_id ,json_encode($new_message));
        }
   }
   
   /**
    * 当客户端断开连接时
    * @param integer $client_id 客户端id
    */

    public static function onClose($client_id){
//        echo $client_id.PHP_EOL;
//       // debug
//       echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id onClose:''\n";
//
//       // 从房间的客户端列表中删除
//       if(isset($_SESSION['room_id']))
//       {
//           $room_id = $_SESSION['room_id'];
//           $new_message = array('type'=>'logout', 'from_client_id'=>$client_id, 'from_client_name'=>$_SESSION['client_name'], 'time'=>date('Y-m-d H:i:s'));
//           Gateway::sendToGroup($room_id, json_encode($new_message));
//       }

        $redis = new MyRedis();

        //判断有登录 在做清除登录信息操作
        if($redis->sIsMember(TomConst::$const['USER_LOGIN_SESSION'],session_id())){
            //不管是主动退出还是F5刷新,都删除client_id,下次登录在添加
            $key1 = sprintf(TomConst::$const['USER_CLIENT_IDS'],session_id());
            $redis->sRem($key1,$client_id);

            global $connection_count;
            $connection_count--;
            Gateway::sendToAll(BaseJson::encode(['total_conn_person'=>$connection_count]));
            $worker = new AsyncTcpConnection('text://0.0.0.0:6868');
            $worker->send(BaseJson::encode(['type'=>'add','client_id'=>$client_id]));
            $worker->connect();
        }else{
            echo 33333;
        }
    }


    public static function onConnect($client_id){
        echo session_id().PHP_EOL;
        echo $client_id;
        global $connection_count;
        $redis = new MyRedis();
        $user_service = new UserService();

        //没有登录信息 先登录
        if($redis->sIsMember(TomConst::$const['USER_LOGIN_SESSION'],session_id())==false){
            $type = 'DO_LOGIN';
            $data = ['msg'=>TomConst::$const[$type],'type'=>$type,
                'total_conn_person'=>is_null($connection_count)?0:$connection_count];
            Gateway::sendToClient($client_id,BaseJson::encode($data));
            return;
        }else{
            $key1 = sprintf(TomConst::$const['USER_CLIENT_IDS'],session_id());
            $clients = $redis->smembers($key1);

            //删除退出的定时器
//            $key = sprintf(TomConst::$const['USER_ALARM'],session_id());
//            $time_ids = $redis->smembers($key);
//            if($time_ids){
                $worker = new AsyncTcpConnection('text://0.0.0.0:6868');
                $worker->send(BaseJson::encode(['client_id'=>$client_id,'type'=>'del']));
                $worker->connect();
//            }

            foreach($clients as $cid){
                $redis->sRem($key1,$cid);
                $type = 'REPEAT_LOGIN';
                $data = ['type'=>$type,'msg'=>TomConst::$const[$type]];
                Gateway::sendToClient($client_id,BaseJson::encode($data));
            }
            $redis->sadd($key1, $client_id);
            ++$connection_count;
        }

        $user = $user_service->get_user();

        $data = ['type'=>'IS_LOGIN','total_conn_person'=>$connection_count,'msg'=>$user['name']];
        Gateway::sendToAll(BaseJson::encode($data));
    }
}
