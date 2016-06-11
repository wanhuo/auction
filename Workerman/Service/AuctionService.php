<?php
/**
 * Created by PhpStorm.
 * User: yaoheng
 * Date: 16/5/27
 * Time: 下午3:17
 * 拍卖逻辑
 */

namespace Workerman\Service;

use \Workerman\Lib\TomConst;


class AuctionService extends UserService{


    public function __construct()
    {
        parent::__construct();
    }

    //出价
    public function bid($info = array()){
        if(!is_array($info))
            return false;

        $key = sprintf(TomConst::$const['WATCH_PRODUCT_PRICE'],$info['pid']);

//        $product_price = $this->_redis->get($key);

        if(!isset($info['price'])){
            return ['msg'=>TomConst::$const['BID_PRICE_ERR1'],'result'=>'err','type'=>'BID'];
        }
//        if($product_price > $info['price']){echo 2;
//            return ['msg'=>TomConst::$const['BID_UPDATE'],'result'=>'err','type'=>'BID'];
//        }

        $user_bid = [
            'uid'=>$info['uid'],
            'price'=>abs($info['price']),//出价价格
            'date'=>date('Y-m-d H:i:s'),//出价时间
            'time'=>time(),
        ];


        $this->_redis->watch($key);
        $user_service = new UserService();

        $user = $user_service->get_user($info['uid']);
        $product = $this->_redis->hgetall('product:1');

        if($product['bid_uid'] == $info['uid'])
            return ['msg'=>TomConst::$const['BID_REPEAT'],'result'=>'err','type'=>'BID'];
        if($product['current_price'] >= abs($info['price']))
            return ['msg'=>TomConst::$const['BID_PRICE_ERR2'],'result'=>'err','type'=>'BID'];

        $this->_redis->multi();

        $this->_redis->set($key,abs($info['price']));
        $rob_result = $this->_redis->exec();
        if($rob_result){
            $this->_redis->hmset('product:1',
                [
                    'current_price'=>$info['price'],
                    'bid_uid'=>$user['uid'],
                    'bid_name'=>$user['name'],
                ]);

            $this->auction_mongo->where(['pid'=>'1'])
                ->set(['bid_uid'=>$user['uid'],'bid_uname'=>$user['name']])
                ->push(['bid_info'=>$user_bid])
                ->update('product');

            return ['msg'=>TomConst::$const['BID_OK'],'result'=>'ok',
                'info'=>$user['name'],'price'=>abs($info['price']),'type'=>'BID'];
        }else{
            return ['msg'=>TomConst::$const['BID_UPDATE'],'result'=>'err','info'=>$user_bid,'type'=>'BID'];
        }
    }
}