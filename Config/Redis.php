<?php
/**
 * Created by PhpStorm.
 * User: yaoheng
 * Date: 16/5/21
 * Time: 下午8:53
 */

namespace Config;

class Redis
{
    // 数据库实例1
    public static $config = [
        'dsn'=>'tcp://127.0.0.1:6379',
        'db'=>15,
    ];
}