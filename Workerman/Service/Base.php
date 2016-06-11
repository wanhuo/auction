<?php
/**
 * Created by PhpStorm.
 * User: yaoheng
 * Date: 16/5/27
 * Time: 下午4:26
 */

namespace Workerman\Service;

use MongoQB\Builder;
use Predis\Client;


class Base{
    protected $wow_mongo;
    protected $_redis;
    protected $auction_mongo;

    function __construct()
    {
        $this->auction_mongo = new Builder();
        $this->wow_mongo = new Builder(['dsn'=>'mongodb://localhost:27017/wow',]);
        $this->_redis = new Client();
    }
}