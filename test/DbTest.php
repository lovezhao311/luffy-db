<?php
use luffyzhao\db\Db;
use PHPUnit\Framework\TestCase;

/**
 *
 */
class DbTest extends TestCase
{
    protected $db = null;
    /**
     * 连接
     * @method   connect
     * @DateTime 2017-08-21T09:52:37+0800
     * @return   [type]                   [description]
     */
    public function setUp()
    {
        $database = [
            // 数据库类型
            'type' => 'mysql',
            // 服务器地址
            'hostname' => 'localhost',
            // 数据库名
            'database' => 'test',
            // 用户名
            'username' => 'root',
            // 密码
            'password' => '123456',
            // 端口
            'hostport' => '3306',
        ];
        $this->db = Db::connect($database);
    }
    /**
     * 测试是否连接成功
     * @method   testConnect
     * @DateTime 2017-08-21T10:03:40+0800
     * @return   [type]                   [description]
     */
    public function testConnect()
    {
        $this->assertTrue(!empty($this->db));
    }
    /**
     * 测试插入
     * @method   testInsert
     * @DateTime 2017-08-21T10:38:27+0800
     * @return   [type]                   [description]
     */
    public function testInsert()
    {
        $res = $this->db->table('test_db')->data('name', '战非')->data('phone', '15215214578')->insert();
        $this->assertTrue(!!$res);
    }

    /**
     * 测试插入
     * @method   testInsert
     * @DateTime 2017-08-21T10:38:27+0800
     * @return   [type]                   [description]
     */
    public function testUpdate()
    {
        $res = $this->db->table('test_db')->data('name', '战非1')->where('id', 'in', 1)->update();
        $this->assertTrue($res !== false);
    }
    /**
     * 测试查找
     * @method   testSelect
     * @DateTime 2017-08-21T11:10:03+0800
     * @return   [type]                   [description]
     */
    public function testSelect()
    {
        $res = $this->db->table('test_db')->where(function ($query) {
            $query->where('phone', 'like', '战2%');
            $query->whereOr('name', 'like', "战非%");
        })->where('name', 'not null')
            ->where('id', 'in', [1, 2, 3, 4, 5])->findAll();

        $this->assertArrayHasKey(0, $res);
        $this->assertArrayHasKey('id', $res[0]);
        $this->assertArrayHasKey('name', $res[0]);
        $this->assertArrayHasKey('phone', $res[0]);
    }
}
