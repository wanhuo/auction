<?php
/**
 * Created by PhpStorm.
 * User: yaoheng
 * Date: 16/5/20
 * Time: 下午6:57
 */

$pid_dir = __DIR__."/pid_files";
if(!is_dir($pid_dir)){
    mkdir($pid_dir);
}
for($i=0; $i<3; $i++){
    $pid = pcntl_fork();
    if($pid == -1){
        var_dump("fork failed");
    }
    if(!$pid){
        //子进程代码
        $pid = posix_getpid();
        $ppid = posix_getppid();
        $r = rand(0,100);  //随机数
        touch("$pid_dir/fork_child_process_{$i}_{$ppid}_{$pid}_{$r}");
    }
}
$pid = posix_getpid();
$ppid = posix_getppid();
$r = rand(0,100); //随机数
touch("$pid_dir/fork_process_pid_{$ppid}_{$pid}_$r");