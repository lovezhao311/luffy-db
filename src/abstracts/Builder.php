<?php
namespace luffyzhao\db\abstracts;

use PDO;

abstract class Builder
{
    // connection对象实例
    protected $connection;
    // 查询对象实例
    protected $query;
    // SQL表达式
    protected $selectSql = 'SELECT%DISTINCT% %FIELD% FROM %TABLE%%FORCE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER%%LIMIT% %UNION%%LOCK%%COMMENT%';
    protected $insertSql = '%INSERT% INTO %TABLE% (%FIELD%) VALUES (%DATA%) %COMMENT%';
    protected $insertAllSql = 'INSERT INTO %TABLE% (%FIELD%) %DATA% %COMMENT%';
    protected $updateSql = 'UPDATE %TABLE% SET %SET% %JOIN% %WHERE% %ORDER%%LIMIT% %LOCK%%COMMENT%';
    protected $deleteSql = 'DELETE FROM %TABLE% %USING% %JOIN% %WHERE% %ORDER%%LIMIT% %LOCK%%COMMENT%';

    /**
     * 构造函数
     * @access public
     * @param Connection    $connection 数据库连接对象实例
     * @param Query         $query      数据库查询对象实例
     */
    public function __construct(Connection $connection, Query $query)
    {
        $this->connection = $connection;
        $this->query = $query;
    }

    /**
     * 获取当前的连接对象实例
     * @access public
     * @return void
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * 获取当前的Query对象实例
     * @access public
     * @return void
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * 字段名分析
     * @access protected
     * @param string $key
     * @param array  $options
     * @return string
     */
    protected function parseKey($key, $options = [])
    {
        return $key;
    }

    /**
     * value分析
     * @access protected
     * @param mixed     $value
     * @param string    $field
     * @return string|array
     */
    protected function parseValue($value, $field = '')
    {
        if (is_string($value)) {
            $value = strpos($value, ':') === 0 && $this->query->isBind(substr($value, 1)) ? $value : $this->connection->quote($value);
        } elseif (is_array($value)) {
            $value = array_map([$this, 'parseValue'], $value);
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_null($value)) {
            $value = 'null';
        }
        return $value;
    }

    /**
     * 生成查询SQL
     * @access public
     * @param array $options 表达式
     * @return string
     */
    public function select($options = [])
    {
        $sql = str_replace(
            ['%TABLE%', '%DISTINCT%', '%FIELD%', '%JOIN%', '%WHERE%', '%GROUP%', '%HAVING%', '%ORDER%', '%LIMIT%', '%UNION%', '%LOCK%', '%COMMENT%', '%FORCE%'],
            [
                $this->parseTable($options['table'], $options),
                $this->parseDistinct($options['distinct']),
                $this->parseField($options['field'], $options),
                $this->parseJoin($options['join'], $options),
                $this->parseWhere($options['where'], $options),
                $this->parseGroup($options['group']),
                $this->parseHaving($options['having']),
                $this->parseOrder($options['order'], $options),
                $this->parseLimit($options['limit']),
                $this->parseUnion($options['union']),
                $this->parseLock($options['lock']),
                $this->parseComment($options['comment']),
                $this->parseForce($options['force']),
            ], $this->selectSql);
        return $sql;
    }

    /**
     * 生成update SQL
     * @access public
     * @param array     $fields 数据
     * @param array     $options 表达式
     * @return string
     */
    public function update($data, $options)
    {
        $table = $this->parseTable($options['table'], $options);
        $data = $this->parseData($data, $options);
        if (empty($data)) {
            return '';
        }
        foreach ($data as $key => $val) {
            $set[] = $key . '=' . $val;
        }

        $sql = str_replace(
            ['%TABLE%', '%SET%', '%JOIN%', '%WHERE%', '%ORDER%', '%LIMIT%', '%LOCK%', '%COMMENT%'],
            [
                $this->parseTable($options['table'], $options),
                implode(',', $set),
                $this->parseJoin($options['join'], $options),
                $this->parseWhere($options['where'], $options),
                $this->parseOrder($options['order'], $options),
                $this->parseLimit($options['limit']),
                $this->parseLock($options['lock']),
                $this->parseComment($options['comment']),
            ], $this->updateSql);

        return $sql;
    }

    /**
     * 生成insert SQL
     * @access public
     * @param array     $data 数据
     * @param array     $options 表达式
     * @param bool      $replace 是否replace
     * @return string
     */
    public function insert(array $data, $options = [], $replace = false)
    {
        // 分析并处理数据
        $data = $this->parseData($data, $options);
        if (empty($data)) {
            return 0;
        }
        $fields = array_keys($data);
        $values = array_values($data);

        $sql = str_replace(
            ['%INSERT%', '%TABLE%', '%FIELD%', '%DATA%', '%COMMENT%'],
            [
                $replace ? 'REPLACE' : 'INSERT',
                $this->parseTable($options['table'], $options),
                implode(' , ', $fields),
                implode(' , ', $values),
                $this->parseComment($options['comment']),
            ], $this->insertSql);

        return $sql;
    }

    /**
     * table分析
     * @access protected
     * @param mixed $tables
     * @param array $options
     * @return string
     */
    protected function parseTable($tables, $options = [])
    {
        $item = [];
        foreach ((array) $tables as $key => $table) {
            if (!is_numeric($key)) {
                $item[] = $this->parseKey($key) . ' ' . $this->parseKey($table);
            } else {
                if (isset($options['alias'][$table])) {
                    $item[] = $this->parseKey($table) . ' ' . $this->parseKey($options['alias'][$table]);
                } else {
                    $item[] = $this->parseKey($table);
                }
            }
        }
        return implode(',', $item);
    }

    /**
     * distinct分析
     * @access protected
     * @param mixed $distinct
     * @return string
     */
    protected function parseDistinct($distinct)
    {
        return !empty($distinct) ? ' DISTINCT ' : '';
    }

    /**
     * field分析
     * @access protected
     * @param mixed     $fields
     * @param array     $options
     * @return string
     */
    protected function parseField($fields, $options = [])
    {
        if ('*' == $fields || empty($fields)) {
            $fieldsStr = '*';
        } elseif (is_array($fields)) {
            // 支持 'field1'=>'field2' 这样的字段别名定义
            $array = [];
            foreach ($fields as $key => $field) {
                if (!is_numeric($key)) {
                    $array[] = $this->parseKey($key, $options) . ' AS ' . $this->parseKey($field, $options);
                } else {
                    $array[] = $this->parseKey($field, $options);
                }
            }
            $fieldsStr = implode(',', $array);
        }
        return $fieldsStr;
    }

    /**
     * where分析
     * @access protected
     * @param mixed $where   查询条件
     * @param array $options 查询参数
     * @return string
     */
    protected function parseWhere($where, $options)
    {
        $whereStr = $this->buildWhere($where, $options);
        return empty($whereStr) ? '' : ' WHERE ' . $whereStr;
    }

    /**
     * 生成查询条件SQL
     * @access public
     * @param mixed     $where
     * @param array     $options
     * @return string
     */
    public function buildWhere($where, $options)
    {
        if (empty($where)) {
            $where = [];
        }

        if ($where instanceof Query) {
            return $this->buildWhere($where->getOptions('where'), $options);
        }

        $whereStr = '';
        foreach ($where as $key => $val) {
            $str = [];
            foreach ($val as $field => $value) {
                if ($value instanceof \Closure) {
                    $query = new Query($this->connection);
                    call_user_func_array($value, [ & $query]);
                    $whereClause = $this->buildWhere($query->getOptions('where'), $options);
                    if (!empty($whereClause)) {
                        $str[] = ' ' . $key . ' ( ' . $whereClause . ' )';
                    }
                } else if (is_array($value)) {
                    list($field, $op, $condition) = $value;
                    $op = strtoupper($op);

                    $bindField = 'where_' . str_replace(['.', '-'], '_', $field);
                    $field = $this->parseKey($field);
                    if ($this->query->isBind($bindField)) {
                        $bindField .= uniqid();
                    }

                    if (in_array($op, ['=', '<>', '>', '>=', '<', '<='])) {
                        // 比较运算
                        $str[] = ' ' . $key . ' ( ' . $field . ' ' . $op . ' :' . $bindField . ' ) ';
                        $this->query->bind($bindField, $this->parseValue($condition), PDO::PARAM_STR);
                    } elseif (in_array($op, ['LIKE', 'NOT LIKE'])) {
                        // 模糊匹配
                        $str[] = ' ' . $key . ' ( ' . $field . ' ' . $op . ' ' . $this->parseValue($condition) . ' ) ';
                    } elseif (in_array($op, ['NOT NULL', 'NULL'])) {
                        // NULL 查询
                        $str[] = ' ' . $key . ' ( ' . $field . ' IS ' . $op . ' ) ';
                    } elseif (in_array($op, ['NOT IN', 'IN'])) {
                        // IN 查询
                        if (is_array($condition)) {
                            $str[] = ' ' . $key . ' ( ' . $field . ' ' . $op . ' ( ' . implode(',', $this->parseValue($condition)) . ' ) ) ';
                        } else {
                            $str[] = ' ' . $key . ' ( ' . $field . ' ' . $op . ' ( ' . $this->parseValue($condition) . ' ) ) ';
                        }
                    }
                } else {
                    // 表达式
                    $str[] = ' ' . $key . ' ( ' . $value . ' ) ';
                }
            }
            $whereStr .= empty($whereStr) ? substr(implode(' ', $str), strlen($key) + 1) : implode(' ', $str);
        }

        return $whereStr;
    }

    /**
     * join分析
     * @access protected
     * @param array $join
     * @param array $options 查询条件
     * @return string
     */
    protected function parseJoin($join, $options = [])
    {
        $joinStr = '';
        if (!empty($join)) {
            foreach ($join as $item) {
                list($table, $type, $on) = $item;
                $condition = [];
                foreach ((array) $on as $val) {
                    if (strpos($val, '=')) {
                        list($val1, $val2) = explode('=', $val, 2);
                        $condition[] = $this->parseKey($val1, $options) . '=' . $this->parseKey($val2, $options);
                    } else {
                        $condition[] = $val;
                    }
                }
                $table = $this->parseTable($table, $options);
                $joinStr .= ' ' . $type . ' JOIN ' . $table . ' ON ' . implode(' AND ', $condition);
            }
        }
        return $joinStr;
    }

    /**
     * 数据分析
     * @access protected
     * @param array     $data 数据
     * @param array     $options 查询参数
     * @return array
     */
    protected function parseData($data, $options)
    {
        if (empty($data)) {
            return [];
        }

        $result = [];
        foreach ($data as $key => $val) {
            $item = $this->parseKey($key, $options);
            if (is_null($val)) {
                $result[$item] = 'NULL';
            } elseif (isset($val[0]) && 'exp' == $val[0]) {
                $result[$item] = $val[1];
            } elseif (is_scalar($val)) {
                $key = str_replace(['.', '-'], '_', $key);
                $this->query->bind('__data__' . $key, $val, PDO::PARAM_STR);
                $result[$item] = ':__data__' . $key;
            }
        }
        return $result;
    }

    /**
     * group分析
     * @access protected
     * @param mixed $group
     * @return string
     */
    protected function parseGroup($group)
    {
        return !empty($group) ? ' GROUP BY ' . $group : '';
    }

    /**
     * having分析
     * @access protected
     * @param string $having
     * @return string
     */
    protected function parseHaving($having)
    {
        return !empty($having) ? ' HAVING ' . $having : '';
    }

    /**
     * order分析
     * @access protected
     * @param mixed $order
     * @param array $options 查询条件
     * @return string
     */
    protected function parseOrder($order, $options = [])
    {
        if (is_array($order)) {
            $array = [];
            foreach ($order as $key => $val) {
                if (is_numeric($key)) {
                    if ('[rand]' == $val) {
                        $array[] = $this->parseRand();
                    } elseif (false === strpos($val, '(')) {
                        $array[] = $this->parseKey($val, $options);
                    } else {
                        $array[] = $val;
                    }
                } else {
                    $sort = in_array(strtolower(trim($val)), ['asc', 'desc']) ? ' ' . $val : '';
                    $array[] = $this->parseKey($key, $options) . ' ' . $sort;
                }
            }
            $order = implode(',', $array);
        }
        return !empty($order) ? ' ORDER BY ' . $order : '';
    }

    /**
     * limit分析
     * @access protected
     * @param mixed $lmit
     * @return string
     */
    protected function parseLimit($limit)
    {
        return (!empty($limit) && false === strpos($limit, '(')) ? ' LIMIT ' . $limit . ' ' : '';
    }

    /**
     * index分析，可在操作链中指定需要强制使用的索引
     * @access protected
     * @param mixed $index
     * @return string
     */
    protected function parseForce($index)
    {
        if (empty($index)) {
            return '';
        }

        if (is_array($index)) {
            $index = join(",", $index);
        }

        return sprintf(" FORCE INDEX ( %s ) ", $index);
    }

    /**
     * union分析
     * @access protected
     * @param mixed $union
     * @return string
     */
    protected function parseUnion($union)
    {
        if (empty($union)) {
            return '';
        }
        $type = $union['type'];
        unset($union['type']);
        foreach ($union as $u) {
            if ($u instanceof \Closure) {
                $sql[] = $type . ' ' . $this->parseClosure($u, false);
            } elseif (is_string($u)) {
                $sql[] = $type . ' ' . $u;
            }
        }
        return implode(' ', $sql);
    }

    // 执行闭包子查询
    protected function parseClosure($call, $show = true)
    {
        $query = new Query($this->connection);
        call_user_func_array($call, [ & $query]);
        return $query->buildSql($show);
    }

    /**
     * 设置锁机制
     * @access protected
     * @param bool $locl
     * @return string
     */
    protected function parseLock($lock = false)
    {
        return $lock ? ' FOR UPDATE ' : '';
    }

    /**
     * comment分析
     * @access protected
     * @param string $comment
     * @return string
     */
    protected function parseComment($comment)
    {
        return !empty($comment) ? ' /* ' . $comment . ' */' : '';
    }

}
