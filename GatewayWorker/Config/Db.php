<?php
/**
 * Created by PhpStorm.
 * User: yaoheng
 * Date: 16/5/10
 * Time: 上午11:40
 */

namespace Config;

class Db
{
    // 数据库实例1
    public static $db1 = array(
        'host' => '127.0.0.1',
        'port' => 3306,
        'user' => 'mysql_user',
        'password' => 'mysql_password',
        'dbname' => 'database_name1',
        'charset' => 'utf8',
    );
}