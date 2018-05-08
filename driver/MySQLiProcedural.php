<?php
namespace driver;
/**
 * 数据库CURD类.mysqli for procedural
 *
 * @author  Nezumi
 *
 * 
 */
class MySQLiProcedural
{

    private $link;   //数据库连接资源

    private $result;  //最近数据库查询资源

    private $config; //数据库连接信息


    public function __construct()
    {

    }

    public function __destruct()
    {
       
    }

    /**
     *  是否自动连接,入口
     * 
     */
    public function open($config)
    {
        if(empty($config)){
            return $this->throw_exception('没有定义数据库配置');
        }
        $this->config = $config;
        if( $this->config['autoconnect'] ){
            return $this->connect();
        }
    }

    /**
     * 连接数据库方法
     * 
     * @access public
     * 
     * @return resource
     * 
     */
    public function connect()
    {
        $this->link = mysqli_connect($this->config['hostname'], $this->config['username'], $this->config['password'], $this->config['database']);
        if( $this->link->connect_error ){
            return $this->throw_exception('连接数据库失败');
        }
        if( !mysqli_set_charset($this->link, $this->config['charset']) ){
            return $this->throw_exception('设置默认字符编码失败');
        }
        return $this->link; 
    }

    /**
     * sql执行
     * 
     * @param string $sql 
     * 
     * @return resource or false
     * 
     */
    public function query($sql)
    {
        if ($sql == '') {
            return false;
        }
        //如果autoconnect关闭，那么连接的时候这里检查来启动mysql实例化
        if (!is_resource($this->link)) {
            $this->connect();
        }
        $this->result = mysqli_query($this->link,$sql);
        return $this->result; 
    }

    /** 
     *  表中插入数据
     * 
     *  @access public
     *  @author Nezumi
     * 
     *  @param $data   array        插入数组
     *  @param $table  string       要插入数据的表名
     *  @param $return_insert_id boolean   是否返回插入ID
     *  @param $replace  boolean 是使用replace into 还是insert into
     * 
     *  @return boolean,query resource,int
     * 
     */
    public function insert( $data, $table, $return_insert_id = false, $replace = false )
    {
        if (empty($data)) {
            return $this->throw_exception('To insert array is required!');
        }
        $fields = array_keys($data);
        $values = array_values($data);

        array_walk($fields, array($this, 'add_special_char'));
        array_walk($values, array($this, 'add_quotation'));

        $fields_str = implode(',', $fields);
        $values_str = implode(',', $values);
        $method = $replace ? 'REPLACE' : 'INSERT';
        $insert_sql = $method.' INTO '.$table.'('.$fields_str.')'.' values('.$values_str.')';
        $return = $this->query($insert_sql);
        return $return_insert_id ? $this->insert_id() : $return;
    }

    /**
     *  表中更新数据
     *
     *  @access public
     *  @author  Nezumi
     *
     *  @param  string $data['tab_name'] 表名
     *  @param  array  $data['update_arr'] 更新数组
     *  @param  array  $data['condition'] = array(
     *  
     *  @return int 影响行数 
     * 
     */
    public function update($data, $table, $where, $return_affected_rows = false)
    {
        if (empty($data)) {
            return $this->throw_exception('To update array is required!');
        } else if (empty($where)) {
            return $this->throw_exception('The condition is required.');
        }
        $data_sql = '';  //更新sql
        //判断条件是否为空
        foreach ($data as $key => $values) {
            $data_sql .= $this->add_special_char($key).'='.$this->add_quotation($values).',';
        }
        $data_sql = substr($data_sql, 0, -1);
        $sql = 'UPDATE '.$table.' SET '.$data_sql.$this->parse_where($where);
        $return = $this->query($sql);
        return $return_affected_rows ? $this->insert_id() : $return;
    }
    
    /**
     * 查询多条记录.
     * 
     * @param string $fields 
     * @param string $table 
     * @param string $where 
     * @param string $limit 
     * @param string $order 
     * @param string $group 
     * @param string $key 
     * 
     * @return type
     * 
     */
    function select($fields='*', $table, $where = '', $limit = '', $order = '', $group = '', $key = '', $having = '') 
    {
        $sql = 'SELECT  '.$this->parse_fields($fields).' FROM '.$table. $this->parse_where($where).$this->parse_group($group).$this->parse_having($having).$this->parse_order($order).$this->parse_limit($limit);
        return $this->fetch_all($sql);
    }

    /**
     * 查询一条记录.
     * 
     * @param string $fields 
     * @param string $table 
     * @param string $where 
     * @param string $limit 
     * @param string $order 
     * @param string $group 
     * @param string $key 
     * 
     * @return type
     * 
     */
    function get_one($fields='*', $table, $where = '', $limit = '', $order = '', $group = '', $key = '', $having = '') 
    {
        $sql = 'SELECT  '.$this->parse_fields($fields).' FROM '.$table. $this->parse_where($where).$this->parse_group($group).$this->parse_having($having).$this->parse_order($order).$this->parse_limit($limit);
        return $this->fetch_one($sql);
    }

    /**
     *  Delete Datas
     *
     *  @param  string $$talbe
     * 
     *  @return int
     * 
     */
    public function delete($table, $where)
    {
        if( empty($where) ){
            return $this->throw_exception('The condition is required.');
        }
        $sql = 'DELETE FROM  '.$table.$this->parse_where($where);
        $this->query($sql);
        return $this->affected_rows();
    }

    /**
     * 查询多条记录.
     * 
     * @param string $sql
     *  
     * @return array
     * 
     */
    public function fetch_all($sql) 
    {
        $this->query($sql);
        $result = array();
        while ($row = $this->fetch()) {
            $result[] = $row;
        }
        return $result;
    }

    /**
     * 查询一条记录
     *
     * @param string $sql 
     * 
     * @return array or false
     * 
     */
    public function fetch_one($sql) 
    {
        $this->query($sql);
        return $this->fetch();
    }

    /**
     * 查询一条记录获取类型
     *
     * @param constant $type 返回结果集类型    
     *                  MYSQL_ASSOC，MYSQL_NUM 和 MYSQL_BOTH
     * 
     * @return array or false
     * 
     */
    public function fetch($type = MYSQLI_ASSOC ){
        $res = mysqli_fetch_array($this->result, $type);
        //如果查询失败，返回False,那么释放改资源
        if(!$res){
            $this->free_result();
        }
        return $res; 
    }

    /**
     * 
     * 释放查询资源
     * 
     * 
     */
    public function free_result(){
       $this->result = NULL;
    }

    /**
     * 根据主键获取一条记录
     *
     * @param string $sql 查询sql
     * @param string $type 类型
     * 
     * @return array or false 
     * 
     */
    public function get_byprimary($table, $primary, $fields = '*') 
    {
        $sql = 'select %s from %s where '.$this->get_primary($table).'=%d';
        $sprintf_sql = sprintf($sql, $this->parse_fields($fields), $table, $primary);
        return  $this->fetch_one($sprintf_sql);
    }   

    /**
     * 获取数据表主键
     * 
     * @param $table  数据表
     * 
     * @return string 
     * 
     */
    public function get_primary($table) 
    {
        $this->query('DESC '.$table);
        while($row = $this->fetch()){
             if( $row['Key']=='PRI' ){
                  $primary = $row['Field']; 
                  break;
             } 
        }
        return $primary;
    }

    /**
     * Parse fields
     *
     * @param string or array 字段添加`
     * 
     * @return string 
     * 
     */
    public function parse_fields($fields){
        $fields_str = '';
        if( is_string($fields) && trim($fields)== '*'){
            $fields_str = '*';
        } else if( is_string($fields) ){
            $arr = explode(',', $fields);
            $fields_str = implode(',', $arr);
        } else if( is_array($fields)  ){
            $fields_str = implode(',', $fields);
        } else {
            $fields_str = '*';
        }
        return $fields_str;
    }
    

    /**
     * Parse where
     *
     * @param string $where 
     * 
     * @return string 
     * 
     */
    public function parse_where($where)
    {
        $where_str = '';
        if( $where == '' ){
            return $where_str;
        } else if( is_string($where) ){
            $where_str = ' where '.$where;
        } 
        return $where_str;
    }

    /**
     * Parse group
     *
     * @param string $group 
     * 
     * @return string 
     * 
     */
    public function parse_group($group)
    {
        $group_str = '';
        if( $group == '' ){
            return $group_str;
        } else if( is_string($group) ){
            $group_str = ' GROUP BY '.$group;
        } else if( is_array($group) ){
            $group_str = ' GROUP BY '.implode(',', $group);
        }
        return $group_str;
    }

    /**
     * Parse having
     *
     * @param string $having 
     * 
     * @return string 
     * 
     */
    public function parse_having($having)
    {
        $having_str = '';
        if( $having == '' ){
            return $having_str;
        } else if( is_string($having) ){
            $having_str = ' HAVING '.$having;
        } 
        return $having_str;
    }

    /**
     * Parse order
     *
     * @param string $order 
     * 
     * @return string 
     * 
     */
    public function parse_order($order)
    {
        $order_str = '';
        if( $order == '' ){
            return $order_str;
        } else if( is_string($order) ){
            $order_str = ' ORDER BY '.$order;
        } else if( is_array($order) ){
            $order_str = ' ORDER BY '.implode(',', $order);
        }
        return $order_str;
    }

    /**
     * Parse limit
     *
     * @param string $limit 
     * 
     * @return string 
     * 
     */
    public function parse_limit($limit)
    {
        $limit_str = '';
        if( $limit == '' ){
            return $limit_str;
        } else if( is_string($limit) || is_numeric($limit) ){
            $limit_str = ' LIMIT '.$limit;
        } else if( is_array($limit) ){
            if( count($limit)==1 ){
                $limit_str = ' LIMIT '.$limit[0];
            } else {
                $limit_str = ' LIMIT '.$limit[0].','.$limit[1];
            }
        }
        return $limit_str;
    }


    /**
     * Add `
     *
     * @param string $fields
     * 
     * @return string 
     * 
     */
    public function add_special_char(&$value){
        if( strpos($value,'`') ===false ){
            $value = '`'.trim($value).'`';
        }
        return $value;
    }


    /**
     * Add ''
     *
     * @param string $fields
     * 
     * @return string 
     * 
     */
    public function add_quotation(&$value, $key = '' , $user_data = '', $quotation=1){
        if($quotation){
            $quot = '\'';
        } else {
            $quot = '';
        }
        $value = $quot.$value.$quot;
        return $value; 
    }

    /**
     * 查询表的总记录条数 total_record(表名)
     * 
     * @param string $table 
     * 
     * @return int
     * 
     */
    public function total_record($table)
    {
        $this->result = $this->query('select * from'.$table);
        return mysqli_num_rows($this->result);
    }

    /**
     * 获取sql在数据库影响的条数
     * 
     * @return int
     * 
     */
    public function affected_rows()
    {
        return mysqli_affected_rows($this->link);
    }

    /**
     * 取得上一步 INSERT 操作表产生的auto_increment,就是自增主键
     * 
     * @return int
     * 
     */
    public function insert_id()
    {
        return mysqli_insert_id($this->link);
    }

   /**
     * 通过sql语句得到的值显示成表格
     * 
     * @param string $sql 
     * 
     * @return string
     * 
     */
    public function display_table($sql)
    {
        $display_que = $this->query($table);
        while ($display_arr = $this->fetch()) {
            $display_result[] = $display_arr;
        }
        $display_out = '';
        $display_out .= '<table border=1><tr>';
        foreach ($display_result as $display_key => $display_val) {
            if (0 == $display_key) {
                foreach ($display_val as $display_ky => $display_vl) {
                    $display_out .= "<td>$display_ky</td>";
                }
            } else {
                break;
            }
        }
        $display_out .= '</tr>';
        foreach ($display_result as $display_k => $display_v) {
            $display_out .= '<tr>';
            foreach ($display_v as $display_kid => $display_vname) {
                $display_out .= "<td> &nbsp;$display_vname</td>";
            }
            $display_out .= '</tr>';
        }
        $display_out .= '</table>';

        return $display_out;
    }

    /**
     * 显示表配置信息(表引擎)
     * 
     * @param string $table 
     * 
     * @return string
     * 
     */
    public function table_config($table)
    {
        $sql = 'SHOW TABLE STATUS from '.$this->config['database'].' where Name=\''.$table.'\'';
        return $this->display_table($table_config_que);
    }

    /**
     * 显示数据库表信息
     * 
     * @param string $table 
     * 
     * @return string
     * 
     */
    public function tableinfo($table)
    {
        $sql = 'SHOW CREATE TABLE '.$table;
        return $this->display_table($sql);;
    }

    /**
     * 显示服务器信息
     * 
     * @param string $table 
     * 
     * @return string
     * 
     */
    public function serverinfo()
    {
        return mysqli_get_server_info($this->link);
    }

   /**
     * 如果调试的话输出错误信息
     * @param string $errMsg 
     * @param string $sql 
     * @return boolean
     */
    public function throw_exception($errMsg = '' , $sql = '')
    {
        if( $this->config['debug'] ){
            $output = ''; 
            echo $sql.$errMsg;
        }
        return false;
    }

    /**
     * 关闭连接
     * @return type
     */
    public function close()
    {
        if(is_resource($this->link)){
            mysqli_close($this->link);
        }
    }

}