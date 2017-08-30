<?php
namespace luffyzhao\db\abstracts;

use luffyzhao\db\abstracts\Connection;
use PDO;

/**
 * Class Query
 * @package luffyzhao
 */
class Query
{
    /**
     * 数据库Connection对象实例
     * @var [type]
     */
    protected $connection;
    /**
     * 数据库Builder对象实例
     * @var [type]
     */
    protected $builder;
    /**
     * 当前数据表名称（含前缀）
     * @var string
     */
    protected $table = '';
    /**
     * 当前数据表名称（不含前缀）
     * @var string
     */
    protected $name = '';
    /**
     * 当前数据表主键
     * @var [type]
     */
    protected $pk;
    /**
     * 当前数据表前缀
     * @var string
     */
    protected $prefix = '';
    /**
     * 查询参数
     * @var array
     */
    protected $options = [];
    /**
     * 参数绑定
     * @var array
     */
    protected $bind = [];

    /**
     * 构造函数
     * @access public
     * @param Connection $connection 数据库对象实例
     * @param string     $model      模型名
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->prefix = $this->connection->getConfig('prefix');
        $this->setBuilder();
    }

    /**
     * 设置当前的数据库Builder对象
     * @access protected
     * @return void
     */
    protected function setBuilder()
    {
        $class = $this->connection->getBuilder();
        $this->builder = new $class($this->connection, $this);
    }
    /**
     * 指定默认的数据表名（不含前缀）
     * @access public
     * @param string $name
     * @return $this
     */
    public function name($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * 指定当前操作的数据表
     * @access public
     * @param mixed $table 表名
     * @return $this
     */
    public function table($table)
    {
        if (is_string($table)) {
            if (strpos($table, ')')) {
                // 子查询
            } elseif (strpos($table, ',')) {
                $tables = explode(',', $table);
                $table = [];
                foreach ($tables as $item) {
                    list($item, $alias) = explode(' ', trim($item));
                    if ($alias) {
                        $this->alias([$item => $alias]);
                        $table[$item] = $alias;
                    } else {
                        $table[] = $item;
                    }
                }
            } elseif (strpos($table, ' ')) {
                list($table, $alias) = explode(' ', $table);

                $table = [$table => $alias];
                $this->alias($table);
            }
        } else {
            $tables = $table;
            $table = [];
            foreach ($tables as $key => $val) {
                if (is_numeric($key)) {
                    $table[] = $val;
                } else {
                    $this->alias([$key => $val]);
                    $table[$key] = $val;
                }
            }
        }
        $this->options['table'] = $table;
        return $this;
    }

    /**
     * 指定数据表别名
     * @access public
     * @param mixed $alias 数据表别名
     * @return $this
     */
    public function alias($alias)
    {
        if (is_array($alias)) {
            foreach ($alias as $key => $val) {
                $this->options['alias'][$key] = $val;
            }
        } else {
            if (isset($this->options['table'])) {
                $table = is_array($this->options['table']) ? key($this->options['table']) : $this->options['table'];
            } else {
                $table = $this->getTable();
            }

            $this->options['alias'][$table] = $alias;
        }
        return $this;
    }

    /**
     * 指定查询字段 支持字段排除和指定数据表
     * @access public
     * @param mixed   $field
     * @param boolean $except    是否排除
     * @param string  $tableName 数据表名
     * @param string  $prefix    字段前缀
     * @param string  $alias     别名前缀
     * @return $this
     */
    public function field($field, $except = false, $tableName = '', $prefix = '', $alias = '')
    {
        if (empty($field)) {
            return $this;
        }
        if (is_string($field)) {
            $field = array_map('trim', explode(',', $field));
        }
        if ($tableName) {
            // 添加统一的前缀
            $prefix = $prefix ?: $tableName;
            foreach ($field as $key => $val) {
                if (is_numeric($key)) {
                    $val = $prefix . '.' . $val . ($alias ? ' AS ' . $alias . $val : '');
                }
                $field[$key] = $val;
            }
        }

        if (isset($this->options['field'])) {
            $field = array_merge($this->options['field'], $field);
        }
        $this->options['field'] = array_unique($field);
        return $this;
    }

    /**
     * 查询SQL组装 join
     * @access public
     * @param mixed  $join      关联的表名
     * @param mixed  $condition 条件
     * @param string $type      JOIN类型
     * @return $this
     */
    public function join($join, $condition = null, $type = 'INNER')
    {
        if (empty($condition)) {
            // 如果为组数，则循环调用join
            foreach ($join as $key => $value) {
                if (is_array($value) && 2 <= count($value)) {
                    $this->join($value[0], $value[1], isset($value[2]) ? $value[2] : $type);
                }
            }
        } else {
            $table = $this->getJoinTable($join);

            $this->options['join'][] = [$table, strtoupper($type), $condition];
        }
        return $this;
    }

    /**
     * 获取Join表名及别名 支持
     * @access public
     * @param array|string $join
     * @return array|string
     */
    protected function getJoinTable($join, &$alias = null)
    {
        // 传入的表名为数组
        if (is_array($join)) {
            list($table, $alias) = each($join);
        } else {
            $join = trim($join);
            if (false !== strpos($join, '(')) {
                // 使用子查询
                $table = $join;
            } else {
                $prefix = $this->prefix;
                if (strpos($join, ' ')) {
                    // 使用别名
                    list($table, $alias) = explode(' ', $join);
                } else {
                    $table = $join;
                    if (false === strpos($join, '.') && 0 !== strpos($join, '__')) {
                        $alias = $join;
                    }
                }
                if ($prefix && false === strpos($table, '.') && 0 !== strpos($table, $prefix) && 0 !== strpos($table, '__')) {
                    $table = $this->getTable($table);
                }
            }
        }
        if (isset($alias)) {
            if (isset($this->options['alias'][$table])) {
                $table = $table . '@think' . uniqid();
            }
            $table = [$table => $alias];
            $this->alias($table);
        }
        return $table;
    }

    /**
     * 指定AND查询条件
     * @access public
     * @param mixed $field     查询字段
     * @param mixed $op        查询表达式
     * @param mixed $condition 查询条件
     * @return $this
     */
    public function where($field, $op = null, $condition = null)
    {
        $this->parseWhereExp('AND', $field, $op, $condition);
        return $this;
    }

    /**
     * 指定OR查询条件
     * @access public
     * @param mixed $field     查询字段
     * @param mixed $op        查询表达式
     * @param mixed $condition 查询条件
     * @return $this
     */
    public function whereOr($field, $op = null, $condition = null)
    {
        $this->parseWhereExp('OR', $field, $op, $condition);
        return $this;
    }
    /**
     * 指定group查询
     * @access public
     * @param string $group GROUP
     * @return $this
     */
    public function group($group)
    {
        $this->options['group'] = $group;
        return $this;
    }
    /**
     * 指定having查询
     * @access public
     * @param string $having having
     * @return $this
     */
    public function having($having)
    {
        $this->options['having'] = $having;
        return $this;
    }

    /**
     * 指定查询lock
     * @access public
     * @param boolean $lock 是否lock
     * @return $this
     */
    public function lock($lock = false)
    {
        $this->options['lock'] = $lock;
        $this->options['master'] = true;
        return $this;
    }

    /**
     * 指定强制索引
     * @access public
     * @param string $force 索引名称
     * @return $this
     */
    public function force($force)
    {
        $this->options['force'] = $force;
        return $this;
    }

    /**
     * 查询注释
     * @access public
     * @param string $comment 注释
     * @return $this
     */
    public function comment($comment)
    {
        $this->options['comment'] = $comment;
        return $this;
    }

    /**
     * 设置从主服务器读取数据
     * @access public
     * @return $this
     */
    public function master()
    {
        $this->options['master'] = true;
        return $this;
    }
    /**
     * 指定distinct查询
     * @access public
     * @param string $distinct 是否唯一
     * @return $this
     */
    public function distinct($distinct)
    {
        $this->options['distinct'] = $distinct;
        return $this;
    }

    /**
     * 指定查询数量
     * @access public
     * @param mixed $offset 起始位置
     * @param mixed $length 查询数量
     * @return $this
     */
    public function limit($offset, $length = null)
    {
        if (is_null($length) && strpos($offset, ',')) {
            list($offset, $length) = explode(',', $offset);
        }
        $this->options['limit'] = intval($offset) . ($length ? ',' . intval($length) : '');
        return $this;
    }

    /**
     * 查询SQL组装 union
     * @access public
     * @param mixed   $union
     * @param boolean $all
     * @return $this
     */
    public function union($union, $all = false)
    {
        $this->options['union']['type'] = $all ? 'UNION ALL' : 'UNION';

        if (is_array($union)) {
            $this->options['union'] = array_merge($this->options['union'], $union);
        } else {
            $this->options['union'][] = $union;
        }
        return $this;
    }

    /**
     * 使用表达式设置数据
     * @access public
     * @param string $field 字段名
     * @param string $value 字段值
     * @return $this
     */
    public function exp($field, $value)
    {
        $this->data($field, ['exp', $value]);
        return $this;
    }

    /**
     * 设置数据
     * @access public
     * @param mixed $field 字段名或者数据
     * @param mixed $value 字段值
     * @return $this
     */
    public function data($field, $value = null)
    {
        if (is_array($field)) {
            $this->options['data'] = isset($this->options['data']) ? array_merge($this->options['data'], $field) : $field;
        } else {
            $this->options['data'][$field] = $value;
        }
        return $this;
    }
    /**
     * 获取当前的查询参数
     * @access public
     * @param string $name 参数名
     * @return mixed
     */
    public function getOptions($name = '')
    {
        if ('' === $name) {
            return $this->options;
        } else {
            return isset($this->options[$name]) ? $this->options[$name] : null;
        }
    }
    /**
     * 去除某个查询条件
     * @access public
     * @param string $field 查询字段
     * @param string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function removeWhereField($field, $logic = 'AND')
    {
        $logic = strtoupper($logic);
        if (isset($this->options['where'][$logic][$field])) {
            unset($this->options['where'][$logic][$field]);
        }
        return $this;
    }

    /**
     * 去除查询参数
     * @access public
     * @param string|bool $option 参数名 true 表示去除所有参数
     * @return $this
     */
    public function removeOption($option = true)
    {
        if (true === $option) {
            $this->options = [];
        } elseif (is_string($option) && isset($this->options[$option])) {
            unset($this->options[$option]);
        }
        return $this;
    }

    /**
     * 创建子查询SQL
     * @access public
     * @param bool $sub
     * @return string
     * @throws DbException
     */
    public function buildSql($sub = true)
    {
        return $sub ? '( ' . $this->findAll(false) . ' )' : $this->findAll(false);
    }

    /**
     * 查找
     * @method   find
     * @DateTime 2017-08-19T11:48:58+0800
     * @param    string                   $value [description]
     * @return   [type]                          [description]
     */
    public function findAll(bool $execute = true)
    {
        $options = $this->parseExpress();
        // 生成查询SQL
        $sql = $this->builder->select($options);

        $bind = $this->getBind();
        if ($execute === false) {
            // 获取实际执行的SQL语句
            return $this->connection->getRealSql($sql, $bind);
        }
        $resultSet = $this->query($sql, $bind, $options['master'], $options['fetch_pdo']);
        return $resultSet;
    }
    /**
     * 更新
     * @method   update
     * @DateTime 2017-08-19T15:57:55+0800
     * @param    bool                     $execute [description]
     * @return   [type]                            [description]
     */
    public function update(bool $execute = true)
    {
        $options = $this->parseExpress();
        // 生成UPDATE SQL语句
        $sql = $this->builder->update($options['data'], $options);
        // 获取参数绑定
        $bind = $this->getBind();
        if ($execute === false) {
            // 获取实际执行的SQL语句
            return $this->connection->getRealSql($sql, $bind);
        }
        // 执行操作
        return '' == $sql ? 0 : $this->execute($sql, $bind);
    }

    /**
     * 插入
     * @method   insert
     * @DateTime 2017-08-19T15:57:55+0800
     * @param    bool                     $execute [description]
     * @param boolean $replace      是否replace
     * @param boolean $getLastInsID 返回自增主键
     * @return   [type]                            [description]
     */
    public function insert(bool $execute = true, bool $getLastInsID = true, bool $replace = false)
    {
        $options = $this->parseExpress();
        // 生成UPDATE SQL语句
        $sql = $this->builder->insert($options['data'], $options, $replace);
        // 获取参数绑定
        $bind = $this->getBind();
        if ($execute === false) {
            // 获取实际执行的SQL语句
            return $this->connection->getRealSql($sql, $bind);
        }
        // 执行操作
        $result = '' == $sql ? false : $this->execute($sql, $bind);
        if ($result && $getLastInsID) {
            $lastInsId = $this->getLastInsID();
            return $lastInsId;
        }
        return $result;
    }

    /**
     * 分析表达式（可用于查询或者写入操作）
     * @access protected
     * @return array
     */
    protected function parseExpress()
    {
        $options = $this->options;

        // 获取数据表
        if (empty($options['table'])) {
            $options['table'] = $this->getTable();
        }

        if (!isset($options['where'])) {
            $options['where'] = [];
        }

        if (!isset($options['field'])) {
            $options['field'] = '*';
        }

        if (!isset($options['data'])) {
            $options['data'] = [];
        }

        if (!isset($options['fetch_pdo'])) {
            $options['fetch_pdo'] = false;
        }

        foreach (['master', 'lock', 'distinct'] as $name) {
            if (!isset($options[$name])) {
                $options[$name] = false;
            }
        }

        foreach (['join', 'union', 'group', 'having', 'limit', 'order', 'force', 'comment'] as $name) {
            if (!isset($options[$name])) {
                $options[$name] = '';
            }
        }

        if (isset($options['page'])) {
            // 根据页数计算limit
            list($page, $listRows) = $options['page'];
            $page = $page > 0 ? $page : 1;
            $listRows = $listRows > 0 ? $listRows : (is_numeric($options['limit']) ? $options['limit'] : 20);
            $offset = $listRows * ($page - 1);
            $options['limit'] = $offset . ',' . $listRows;
        }

        $this->options = [];
        return $options;
    }

    /**
     * 分析查询表达式
     * @access public
     * @param string                $logic     查询逻辑 and or xor
     * @param string|\Closure $field     查询字段
     * @param mixed                 $op        查询表达式
     * @param mixed                 $condition 查询条件
     * @return void
     */
    protected function parseWhereExp($logic, $field, $op, $condition)
    {
        $logic = strtoupper($logic);
        if ($field instanceof \Closure) {
            $this->options['where'][$logic][] = $field;
            return;
        } else {
            $this->options['where'][$logic][] = [$field, $op, $condition];
        }
    }
    /**
     * 得到当前或者指定名称的数据表
     * @access public
     * @param string $name
     * @return string
     */
    public function getTable($name = '')
    {
        if ($name || empty($this->table)) {
            $name = $name ?: $this->name;
            $tableName = $this->prefix;
            if ($name) {
                $tableName .= $name;
            }
        } else {
            $tableName = $this->table;
        }
        return $tableName;
    }
    /**
     * USING支持 用于多表删除
     * @access public
     * @param mixed $using
     * @return $this
     */
    public function using($using)
    {
        $this->options['using'] = $using;
        return $this;
    }

    /**
     * 参数绑定
     * @access public
     * @param mixed   $key   参数名
     * @param mixed   $value 绑定变量值
     * @param integer $type  绑定类型
     * @return $this
     */
    public function bind($key, $value = false, $type = PDO::PARAM_STR)
    {
        if (is_array($key)) {
            $this->bind = array_merge($this->bind, $key);
        } else {
            $this->bind[$key] = [$value, $type];
        }
        return $this;
    }

    /**
     * 获取绑定的参数 并清空
     * @access public
     * @return array
     */
    public function getBind()
    {
        $bind = $this->bind;
        $this->bind = [];
        return $bind;
    }

    /**
     * 检测参数是否已经绑定
     * @access public
     * @param string $key 参数名
     * @return bool
     */
    public function isBind($key)
    {
        return isset($this->bind[$key]);
    }
    /**
     * 获取数据表信息
     * @access public
     * @param mixed  $tableName 数据表名 留空自动获取
     * @param string $fetch     获取信息类型 包括 fields type bind pk
     * @return mixed
     */
    public function getTableInfo($tableName = '', $fetch = '')
    {
        if (!$tableName) {
            $tableName = $this->getTable();
        }
        if (is_array($tableName)) {
            $tableName = key($tableName) ?: current($tableName);
        }

        if (strpos($tableName, ',')) {
            // 多表不获取字段信息
            return false;
        }

        // 修正子查询作为表名的问题
        if (strpos($tableName, ')')) {
            return [];
        }

        list($guid) = explode(' ', $tableName);
        $db = $this->getConfig('database');

        $info = $this->connection->getFields($guid);
        $fields = array_keys($info);
        $bind = $type = [];
        foreach ($info as $key => $val) {
            // 记录字段类型
            $type[$key] = $val['type'];
            $bind[$key] = $this->getFieldBindType($val['type']);
            if (!empty($val['primary'])) {
                $pk[] = $key;
            }
        }
        if (isset($pk)) {
            // 设置主键
            $pk = count($pk) > 1 ? $pk : $pk[0];
        } else {
            $pk = null;
        }
        $info[$db . '.' . $guid] = ['fields' => $fields, 'type' => $type, 'bind' => $bind, 'pk' => $pk];

        return $fetch ? $info[$db . '.' . $guid][$fetch] : $info[$db . '.' . $guid];
    }

    /**
     * 获取当前数据表的主键
     * @access public
     * @param string|array $options 数据表名或者查询参数
     * @return string|array
     */
    public function getPk($options = '')
    {
        if (!empty($this->pk)) {
            $pk = $this->pk;
        } else {
            $pk = $this->getTableInfo(is_array($options) ? $options['table'] : $options, 'pk');
        }
        return $pk;
    }

    /**
     * 获取当前数据表字段信息
     * @method   getTableFields
     * @DateTime 2017-08-21T12:03:22+0800
     * @param    array $options 查询参数
     * @return   array
     * @throws SqlException
     * @throws PDOException
     */
    public function getTableFields($options)
    {
        return $this->getTableInfo($options['table'], 'fields');
    }

    /**
     * 获取当前数据表字段类型
     * @method   getFieldsType
     * @DateTime 2017-08-21T12:04:15+0800
     * @param    array $options 查询参数
     * @return   array
     * @throws SqlException
     * @throws PDOException
     */
    public function getFieldsType($options)
    {
        return $this->getTableInfo($options['table'], 'type');
    }

    /**
     * 获取当前数据表绑定信息
     * @method   getFieldsBind
     * @DateTime 2017-08-21T12:05:43+0800
     * @param    array $options 查询参数
     * @return   array
     * @throws SqlException
     * @throws PDOException
     */
    public function getFieldsBind($options)
    {
        $types = $this->getFieldsType($options);
        $bind = [];
        if ($types) {
            foreach ($types as $key => $type) {
                $bind[$key] = $this->getFieldBindType($type);
            }
        }
        return $bind;
    }
    /**
     * 获取字段绑定类型
     * @access public
     * @param string $type 字段类型
     * @return integer
     */
    protected function getFieldBindType($type)
    {
        if (preg_match('/(int|double|float|decimal|real|numeric|serial|bit)/is', $type)) {
            $bind = PDO::PARAM_INT;
        } elseif (preg_match('/bool/is', $type)) {
            $bind = PDO::PARAM_BOOL;
        } else {
            $bind = PDO::PARAM_STR;
        }
        return $bind;
    }

    /**
     * 执行查询 返回数据集
     * @access public
     * @param string      $sql    sql指令
     * @param array       $bind   参数绑定
     * @param boolean     $master 是否在主服务器读操作
     * @param bool|string $class  指定返回的数据集对象
     * @return mixed
     * @throws BindParamException
     * @throws PDOException
     */
    public function query($sql, $bind = [], $master = false, $class = false)
    {
        return $this->connection->query($sql, $bind, $master, $class);
    }

    /**
     * 执行语句
     * @access public
     * @param string $sql  sql指令
     * @param array  $bind 参数绑定
     * @return int
     * @throws BindParamException
     * @throws PDOException
     */
    public function execute($sql, $bind = [])
    {
        return $this->connection->execute($sql, $bind);
    }

    /**
     * 获取最近插入的ID
     * @access public
     * @param string $sequence 自增序列名
     * @return string
     */
    public function getLastInsID($sequence = null)
    {
        return $this->connection->getLastInsID($sequence);
    }

    /**
     * 获取最近一次查询的sql语句
     * @access public
     * @return string
     */
    public function getLastSql()
    {
        return $this->connection->getLastSql();
    }

    /**
     * 执行数据库事务
     * @access public
     * @param callable $callback 数据操作方法回调
     * @return mixed
     */
    public function transaction($callback)
    {
        return $this->connection->transaction($callback);
    }

    /**
     * 启动事务
     * @access public
     * @return void
     */
    public function startTrans()
    {
        $this->connection->startTrans();
    }

    /**
     * 用于非自动提交状态下面的查询提交
     * @access public
     * @return void
     * @throws PDOException
     */
    public function commit()
    {
        $this->connection->commit();
    }

    /**
     * 事务回滚
     * @access public
     * @return void
     * @throws PDOException
     */
    public function rollback()
    {
        $this->connection->rollback();
    }

    /**
     * 批处理执行SQL语句
     * 批处理的指令都认为是execute操作
     * @access public
     * @param array $sql SQL批处理指令
     * @return boolean
     */
    public function batchQuery($sql = [])
    {
        return $this->connection->batchQuery($sql);
    }

    /**
     * 获取数据库的配置参数
     * @access public
     * @param string $name 参数名称
     * @return boolean
     */
    public function getConfig($name = '')
    {
        return $this->connection->getConfig($name);
    }

    /**
     *
     */
    public function __destruct()
    {
        $this->connection->debug('query destruct');
    }
}
