<?php
namespace luffyzhao\db\exception;

use Exception;

/**
 *
 */
class SqlException extends Exception
{

    /**
     * 保存异常页面显示的额外Debug数据
     * @var array
     */
    protected $data = [];

    public function __construct($message, $config, $sql, $bind, $code = 10502)
    {
        $this->setData('Bind Param', $bind);
        $this->setData('Database Status', [
            'Error Code' => $code,
            'Error Message' => $message,
            'Error SQL' => $sql,
        ]);
        $this->setData('Database Config', $config);
        $this->message = $message;
        $this->code = $code;
    }

    /**
     * 设置异常额外的Debug数据
     * 数据将会显示为下面的格式
     * @param string $label 数据分类，用于异常页面显示
     * @param array  $data  需要显示的数据，必须为关联数组
     */
    protected function setData($label, array $data)
    {
        $this->data[$label] = $data;
    }

    /**
     * 获取异常额外Debug数据
     * 主要用于输出到异常页面便于调试
     * @return array 由setData设置的Debug数据
     */
    public function getData()
    {
        return $this->data;
    }
}
