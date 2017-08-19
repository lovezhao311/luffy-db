<?php
include '../vendor/autoload.php';

use luffyzhao\db\Db;

$database = [
    // 数据库类型
    'type' => 'mysql',
    // 服务器地址
    'hostname' => '192.168.2.242',
    // 数据库名
    'database' => 'fzhd',
    // 用户名
    'username' => 'fangzhou',
    // 密码
    'password' => 'fangzhou@201609',
    // 端口
    'hostport' => '3306',
];

$db = Db::connect($database);

// $db->table("base_client_info")->field('id')
//     ->where('category_id', '=', '6')
//     ->limit(10)->findAll(false);

$res = $db->table("base_client_info")
    ->data('category_id', 1000)
    ->insert(false);

print_r($res);
