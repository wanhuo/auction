<?php
/**
 * Created by PhpStorm.
 * User: yaoheng
 * Date: 16/5/25
 * Time: 下午3:08
 */

namespace Workerman\Service;

use GatewayWorker\Lib\Gateway;
use Predis\Client;
use Workerman\Lib\BaseJson;
use \Workerman\Lib\TomConst;

class UserService extends Base{

    public function __construct()
    {
        parent::__construct();
    }

    public function findOne($param = array()){
        if(is_array($param) == false)
            return false;

        return $this->wow_mongo->getWhere('user',$param );
    }

    public function CheckPwd($user){
        unset($user['type']);unset($user['session_id']);
        $mongo_user = current($this->findOne($user));
        if(false == $mongo_user)
            return false;

        if($user['password'] != $mongo_user['password'])
            return false;
        
        return $mongo_user;
    }

    /***
     * @param $redis Client
     * @param $client_id
     * @param $uid
     */
    public function logout($redis,$client_id,$uid){

        global $connection_count;
        $connection_count--;

        $user = $this->get_user($uid);
        $redis->srem(TomConst::$const['USER_LOGIN_SESSION'],$user['session_id']);

        $type = 'IS_LOGOUT';
        $msg = ['type'=>$type, 'msg'=>sprintf(TomConst::$const[$type],$user['name'])];

        $redis->del(sprintf('user_%s_client',$user['uid']));
        Gateway::sendToClient($client_id,BaseJson::encode($msg));
        Gateway::sendToAll(BaseJson::encode(['total_conn_person'=>$connection_count,]));

        $redis->del(sprintf(TomConst::$const['USER_CLIENT_IDS'],$user['session_id']));

        $this->_redis->del(sprintf(TomConst::$const['USER_INFO_UID'],$user['uid']));
        $this->_redis->del(sprintf(TomConst::$const['USER_INFO_SID'],$user['session_id']));

    }

    public function set_user($user){
        $data = [
            'name'=>$user['name'],
            'uid'=>$user['id'],
            'session_id'=>$user['session_id'],
            'client_id'=>$user['client_id'],
        ];
//        var_dump(sprintf(TomConst::$const['USER_INFO_SID'], $user['session_id']),
//            sprintf(TomConst::$const['USER_INFO_UID'],$user['id']));
        $this->_redis->hmset(sprintf(TomConst::$const['USER_INFO_UID'],$user['id']),$data);
        $this->_redis->hmset(sprintf(TomConst::$const['USER_INFO_SID'],$user['session_id']),$data);

//        var_dump($this->get_user($data['session_id']),$this->get_user($data['uid']));
    }

    public function get_user($id){
        $type = strlen($id)<20?'USER_INFO_UID':'USER_INFO_SID';
        return $this->_redis->hgetall(sprintf(TomConst::$const[$type],$id));
    }
}