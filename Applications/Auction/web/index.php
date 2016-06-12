<?php
error_reporting(E_ALL);
ini_set('display_errors',true);
require_once __DIR__ .'/../../../Predis/autoload.php';
require_once __DIR__ .'/../../../Workerman/Autoloader.php';
$redis = new Predis\Client();
$product = $redis->hgetall('product:1');
if(!$product){
    $redis->hmset('product:1', [
        'pid'=>1,
        'name'=>'包',
        'price'=>600,
        'start_price'=>150,
        'current_price'=>0,
        'bid_uid'=>0,
        'bid_name'=>'',
    ]);
}
$sid = session_id();
$login = $redis->sismember(\Workerman\Lib\TomConst::$const['USER_LOGIN_SESSION'],$sid);

if(!$product['bid_name']) $product['bid_name'] = '暂无出价';
?>

<html xmlns="http://www.w3.org/1999/html">
<head>
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <script src="js/jquery-1.11.2.min.js"></script>
    <script src="js/cookie.js"></script>
    <script src="js/common.js"></script>
    <link href="css/common.css" rel="stylesheet" media="all">
    <script>
        var ws = new WebSocket("ws://localhost:8686");
        ws.onopen = function(evt)
        {
            if('<?php echo $login?>' == 1){
                var data = {'session_id':'<?php echo session_id()?>','type':'CONN_LOGIN'};
                ws.send(JSON.stringify(data));
            }else{
                console.log(3333);
                //登录信息
                $('.theme-popover-mask').fadeIn(100);
                $('.theme-popover').slideDown(200);
            }
        };
        ws.onmessage = function(evt)
        {
            var obj = (JSON.parse(evt.data));

            console.log(obj);
            if(obj.type=='SHOW_MSG'){
                $('#ct').append(
                    '<div>"'+obj.content+'" "'+obj.time+'"</div>'
                );
            }else if(obj.type == 'DO_LOGIN'){
                //登录信息
                $('.theme-popover-mask').fadeIn(100);
                $('.theme-popover').slideDown(200);
            }else if(obj.type =='IS_LOGIN'){
                $('.theme-popover-mask').fadeOut(100);
                $('.theme-popover').slideUp(200);

                //欢迎信息
                $('#login_user').show();
                var html = '当前用户:<span>'+obj.msg+'</span>' +
                    '&nbsp;<a href="#" class="logout">点我退出</a>';
                $('#login_user').html(html);
                console.log(obj);
            }else if(obj.type == 'ONLY_ONE_LOGIN'
                    || obj.type == 'REPEAT_LOGIN'
                || obj.type == 'IS_LOGOUT'
                || obj.type == 'TYPE_ERR'
                || obj.type == 'ERR_LOGIN'){
                alert(obj.msg);
                if(obj.type == 'REPEAT_LOGIN'){
                    CloseWebPage();
                }
                $('.theme-popover-mask').fadeIn(100);
                $('.theme-popover').slideDown(200);
            }else if(obj.type == 'BID'){
                if(obj.result == 'ok'){
                    //更新最新价格
                    $('#bid_user').html(obj.info);
                    $('#bid_price').html(obj.price);
                }else if('ok2'){
                    alert(obj.msg);
                }

            }
//            $('#people_num').html(obj.total_conn_person);
        };

        ws.onclose = function(evt)
        {
            var obj = (JSON.parse(evt.data));
            $('#people_num').html(obj.total_conn_person);
            console.log("WebSocketClosed!");
        };
        ws.onerror = function(evt)
        {
            console.log("WebSocketError!");
        };

        $(function(){
            $('#send').click(function(){
                var content= $('#nrong').val();
                if(content==''){
                    alert('内容不能为空');
                    return false;
                }
                $('#nrong').val('');
                var data = {'type':'SEND','content':content};
                var json = JSON.stringify(data);
                console.log(json);
                ws.send(json);
            });

            $('.theme-poptit .close').click(function(){
                $('.theme-popover-mask').fadeOut(100);
                $('.theme-popover').slideUp(200);
            });

            $('.btn-login').click(function(){console.log('btn');
                var name = $('.user-name').val();
                var pwd = $('.user-pwd').val();
                var data = {'type':'DO_LOGIN','name':name,'password':pwd,'session_id':'<?php echo $sid?>'};
                var json = JSON.stringify(data);
                console.log(json);
                ws.send(json);
            });

            $('#login_user').on('click',function(){
                var data = {'type':'DO_LOGOUT'};
                var json = JSON.stringify(data);
                $('#login_user').html('');
                ws.send(json);
            });
            $('#bid').click(function(){
                var price = parseInt($('#price').val());
                var data = {'type':'BID_ADD','price':price};
                var json = JSON.stringify(data);
                ws.send(json);
            });
        });


    </script>
    <style>
        .dangqian, .dangqian *{display: inline-block; vertical-align: middle;}
        .dangqian div{width: 45%; vertical-align: middle;}
    </style>
</head>
<body>
<div>
    <h3 style="padding-left: 13px;">拍卖demo,使用websocket+redis+mongo,<span style="color: goldenrod">默认账户1:yao,123456|2:php,654321</span></h3>
    <h4 style="padding-left: 13px;" id="login_user"></h4>
</div>
<div id="ltian">
    <div id="us" class="jb"></div>
    <div id="ct"></div>
    <a href="javascript:;" class="qp" onClick="this.parentNode.children[1].innerHTML=''">清屏</a>
</div>
<div id="shopBox" style="line-height: 30px;">
    <h2><?php echo $product['name']?></h2>
    <div class="dangqian">
        <div>
            <img src="img/bao.png" style="width:50%;" alt="">
        </div>
<!--        <div>当前价格:--><?php //echo $product['name']?><!--RMB</div>-->
    </div>
    <p>原价格:<?php echo $product['price']?>RMB</p>
    <p>起拍价:<?php echo $product['start_price']?>RMB</p>
    <p style="color:#ff6600;">当前出价最高:<span id="bid_price"><?php echo $product['current_price']?></span>&nbsp;RMB</p>
    <p style="color:#ff6600;">出价用户:<span id="bid_user"><?php echo $product['bid_name'];?></span></p>
    <div style="display: inline-block margin-top:20px;">
        <h3>我要出价</h3>
        <input style="width:60%;" type="text" id="price" placeholder="请输入您的价格">
        <button style="width:30%;" id="bid">出价</button>
    </div>
</div>
<div class="rin">
    <button id="send">发送</button>
    <span><img src="img/t.png" title="表情" id="imgbq"></span>
    <p><input id="nrong"></p>
</div>
<div id="ems"><p></p><p class="tc"></p>
</div>

<!--<div class="aaa">总在线人数:<span id="people_num"></span></div>-->

<div class="theme-popover">
    <div class="theme-poptit">
        <a href="javascript:;" title="关闭" class="close">×</a>
        <h3>登录 是一种态度</h3>
    </div>
    <div class="theme-popbod dform">
        <form class="theme-signin" name="loginform" action="" method="post">
            <ol>
                <li><h4>你必须先登录！</h4></li>
                <li><strong>用户名：</strong><input class="ipt user-name" type="text" name="name" value="" size="20" /></li>
                <li><strong>密码：</strong><input class="ipt user-pwd" type="password" name="pwd" value="" size="20" /></li>
                <li><input class="btn btn-primary btn-login" type="button" name="submit" value=" 登 录 " /></li>
            </ol>
        </form>
    </div>
</div>
<div class="theme-popover-mask"></div>


</body>
</html>