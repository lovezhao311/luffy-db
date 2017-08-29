<?php
namespace luffyzhao\db;

use luffyzhao\db\abstracts\Connection;

class Db
{
    protected $db = null;

    /**
     * 数据库初始化 并取得数据库类实例
     * @static
     * @access public
     * @param mixed         $config 连接配置
     * @param bool|string   $name 连接标识 true 强制重新连接
     * @return Connection
     * @throws Exception
     */
    public function __construct(array $config)
    {
        // 解析连接参数 支持数组和字符串
        $options = $this->parseConfig($config);
        if (empty($options['type'])) {
            throw new \InvalidArgumentException('Underfined db type');
        }
        $class = false !== strpos($options['type'], '\\') ? $options['type'] : '\\luffyzhao\\db\\connector\\' . ucwords($options['type']);
        $this->db = new $class($options);
    }

    /**
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->db, $name], $arguments);
    }

    /**
     * 数据库连接参数解析
     * @static
     * @access private
     * @param mixed $config
     * @return array
     */
    private function parseConfig($config)
    {
        if (is_string($config)) {
            return $this->parseDsn($config);
        } else {
            return $config;
        }
    }
    /**
     * DSN解析
     * 格式： mysql://username:passwd@localhost:3306/DbName?param1=val1&param2=val2#utf8
     * @static
     * @access private
     * @param string $dsnStr
     * @return array
     */
    private function parseDsn($dsnStr)
    {
        $info = parse_url($dsnStr);
        if (!$info) {
            return [];
        }
        $dsn = [
            'type' => $info['scheme'],
            'username' => isset($info['user']) ? $info['user'] : '',
            'password' => isset($info['pass']) ? $info['pass'] : '',
            'hostname' => isset($info['host']) ? $info['host'] : '',
            'hostport' => isset($info['port']) ? $info['port'] : '',
            'database' => !empty($info['path']) ? ltrim($info['path'], '/') : '',
            'charset' => isset($info['fragment']) ? $info['fragment'] : 'utf8',
        ];

        if (isset($info['query'])) {
            parse_str($info['query'], $dsn['params']);
        } else {
            $dsn['params'] = [];
        }
        return $dsn;
    }

    public function __destruct()
    {
        $this->db->__destruct();
    }
}
