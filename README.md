## luffyzhao 数据库操作类
`luffyzhao-db`是一个数据库操作类，支持各种数据库的增、删、改、查、执行存储过程等。目前还只支持`mysql`。欢迎star、fork、试用 


* 支持mysql
* 支持分布式布局
* 支持读写分离
* 支持日志输出

#### 安装
 ```
    composer require luffyzhao/luffy-db
 ```
    或者
```
    git@github.com:lovezhao311/luffy-db.git
```
#### 环境要求
* php 7.0 +
#### 使用方法
```php
    require __DIR__ . '/../vendor/autoload.php';
    use luffyzhao\db\Db;

    /**
     * 测试读写分离和分布式
     */
    $database = [
        // 数据库类型
        'type' => 'mysql',
        // 服务器地址
        'hostname' => '127.0.0.1,127.0.0.1',
        // 数据库名
        'database' => 'test,test1',
        // 用户名
        'username' => 'root',
        // 密码
        'password' => '123456',
        // 端口
        'hostport' => '3306',
        // 分布式
        'deploy' => 1,
        // 读写分享
        'rw_separate' => true,
        // 调试
        'debug' => function ($messgaes) {
            echo "[" . date('Y-m-d H:i:s') . "]" . $messgaes . "\n";
        },
    ];
    $db = new Db($database);
    //插入数据
    $res = $db->table('test_db')->data('name', '战非')->data('phone', '15215214578')->insert();

    // 没有数据
    $res = $db->table('test_db')->where('phone', '=', '15215214578')->findAll();
    // 有数据 (事务开始之后拿主库里的数据)
    $db->startTrans();
    $res = $db->table('test_db')->findAll();
    $db->commit();

```
