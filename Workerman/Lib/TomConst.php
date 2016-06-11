<?php
/**
 * Created by PhpStorm.
 * User: yaoheng
 * Date: 16/5/25
 * Time: 下午5:56
 */

namespace Workerman\Lib;

class TomConst{

    static $const = [
        'DO_LOGIN' => '请登录',//登录动作
        'IS_LOGIN' => '已登录',//已登录状态
        'ERR_LOGIN' => '登录失败,请重新登录',//登录失败
        'SEND' => '消息发送',//发消息
        'SHOW_MSG' => '消息显示',
        'PING' => '',//心跳检测
        'TYPE_ERR' => '异常,请重新登录',//数据类型异常
        'CONNECTION_NUM' => '',//当前连接数量
        'REPEAT_LOGIN' => '您打开了多个页面,为了不影响您的正常体验,请关闭其他页面',//
        'USER_LOGIN_SESSION' => 'user_login_session',//存放登录用户
        'USER_LOGIN_LIST' => 'user_login_list',//存放在线用户 用于统计人数
        'USER_CLIENT_IDS' => 'user_clients_%s',//key 根据用户id 得到当前用户打开了多少连接 已保证只运行1个连接
        'USER_ALARM' => 'user_alarm_%s',
        'DO_LOGOUT' => '退出操作',
        'IS_LOGOUT' => '用户 %s 已退出',
        'USER_INFO_UID' => 'user_info_u%s',//UID对应用户信息
        'USER_INFO_SID' => 'user_info_s%s',//SessionID对应用户信息
        'BID_REPEAT' => '重复出价,请等待其他用户出价后再次出价',
        'BID_OK' => '您已经出价成功',
        'BID_UPDATE' => '当前价格已经更新,请重新出价',
        'BID_PRICE_ERR1' => '出价价格错误,请重新出价',
        'BID_PRICE_ERR2' => '出价小于当前价格,请重新出价',
        'BID_ADD' => '出价动作',
        'WATCH_PRODUCT_PRICE' => 'watch_product_price_%s',//监控商品拍卖价格
        'CONN_LOGIN'=>'重新登录',
        'ONLY_ONE_LOGIN' => '只允许登录一个账号',
    ];


}