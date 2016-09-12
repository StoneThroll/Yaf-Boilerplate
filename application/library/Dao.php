<?php

/**
 * Data Access Object
 * 简单易用的数据访问对象, 在PDO基础上进行封装
 * Created by PhpStorm.
 * User: shaolei@huatu.com
 * Date: 16/9/8
 * Time: 下午4:37
 */
class Dao
{
    public static $_instances = array();

    /**
     * 建立数据库连接
     */

    //使用短链接访问数据库
    public static function Connect($host, $port, $dbname, $user, $passwd, $charset='utf8')
    {
        try{
            $pdo = new PDO(
                'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbname,
                $user,
                $passwd,
                array(PDO::ATTR_PERSISTENT => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
            if( $charset ) {
                $pdo->exec("SET NAMES {$charset}");
            }
            return $pdo;
        }catch(PDOException $e){
            throw new Exception("DB connect error, msg:" . $e->getMessage());
        }
    }


    public static function setDefaultDb( $dbcfg ) {
        Dao::setInstance( 'default', $dbcfg );
    }

    public static function setInstance( $dbname, $dbcfg ) {
        $ins = new Dao( $dbcfg );
        Dao::$_instances[$dbname] = $ins;
    }

    /**
     * @param string $dbname
     * @return Dao
     * @throws Exception
     */
    public static function db( $dbname='default' ) {
        if( !isset(Dao::$_instances[$dbname]) ) {
            throw new Exception("Db $dbname not found");
        }
        $ins = Dao::$_instances[$dbname];

        if( !$ins->pdo() ) {
            $ins->initPdo();
        }
        return $ins;
    }

    private $_cfg;
    /**
     * @var PDO
     */
    private $_pdo;
    /**
     * @var _Sql
     */
    private $_sql;
    /**
     * @var PDOStatement
     */
    private $_stmt;

    private function __construct( $dbcfg ) {
        $this->_cfg = $dbcfg;
    }
    
    public function initPdo() {
        $cfg = $this->_cfg;
        $this->_pdo = Dao::Connect(
            $cfg['host'], $cfg['port'], $cfg['dbname'], $cfg['user'], $cfg['passwd']
        );
    }

    /**
     * @return PDO
     */
    public function pdo() {
        return $this->_pdo;
    }

    /**
     * @return PDOStatement
     */
    public function statement() {
        return $this->_stmt;
    }

    /**
     * 上一条SQL执行是否成功
     * @return mixed
     */

    public function lastResult() {
        if( $this->_stmt ) {
            $errCode = $this->_stmt->errorCode();
            if( $errCode == 0 ) {// errcode为 空字符串 或 '00000' 代表成功
                return true;
            }
        }
        return false;
    }

    public function lastInsertId()
    {
        return $this->_pdo->lastInsertId();
    }

    public function queryString() {
        return $this->_stmt->queryString;
    }

    public function bindValues() {
        return $this->_sql->bindValues();
    }

    private function _execute() {
        $this->_stmt = $this->_pdo->prepare($this->_sql->sql());
        if( !$this->_stmt ) {
            throw new Exception('Sql has not prepared!');
        }
        $this->_stmt->execute( $this->_sql->bindValues() );
    }

    public function select( array $params ) {
        $this->_sql = new _Select( $params );
        $this->_execute();
        return $this;
    }
    
    public function insert( array $params ) {
        $this->_sql = new _Insert( $params );
        $this->_execute();
        return $this;
    }

    public function update( array $params ) {
        $this->_sql = new _Update( $params );
        $this->_execute();
        return $this;
    }

    public function delete( array $params ) {
        $this->_sql = new _Delete( $params );
        $this->_execute();
        return $this;
    }

    /**
     * @param int $style
     * @return mixed
     */
    public function fetch( $style=PDO::FETCH_ASSOC ) {
        return $this->_stmt->fetch( $style );
    }

    /**
     * @param int $style
     * @return array
     */
    public function fetchAll( $style=PDO::FETCH_ASSOC ) {
        return $this->_stmt->fetchAll( $style );
    }

    /**
     * 获取单一列值( 只获取第一条结果集的第一个列值 )
     * @return mixed
     */
    public function fetchColumn() {
        return $this->_stmt->fetch( PDO::FETCH_COLUMN );
    }

    //事务处理
    /**
     * return bool
     */
    public function start()
    {
        return $this->_pdo->beginTransaction();
    }

    /**
     *
     * @return bool
     */
    public function commit()
    {
        return $this->_pdo->commit();
    }

    /**
     *
     * @return bool
     */
    public function isIn()
    {
        return $this->_pdo->inTransaction();
    }

    /**
     *
     * @return bool
     */
    public function rollback()
    {
        return $this->_pdo->rollBack();
    }
}

/*
 * MySQL 的 SQL 生成器
 */

abstract class _Sql {
    protected $_table;
    protected $_sql;
    protected $_bindValues = array();
    protected $_where = array();
    protected $_values = array();

    public function __construct( array $params ) {
        $table = isset( $params['table'] ) ? $params['table'] : null;
        if( !isset($params['table']) || !is_string( $params['table'] ) )
            throw new Exception("Table name invalid: " . $table);
        $this->_table = $table;
    }

    public function sql() {
        if( !$this->_sql ) {
            $this->_generateSql();
        }
        return $this->_sql;
    }

    public function bindValues() {
        if( !$this->_sql ) {
            $this->_generateSql();
        }
        return $this->_bindValues;
    }

    abstract protected function _generateSql();

    protected function _generateWhere() {
        $expresses = array();

        foreach( $this->_where as $key=>$exp ) {
            if( is_scalar($exp) ) {
                $expresses[] = "`{$key}` = :{$key}";
                $this->_bindValues[$key] = $exp;
            }else if( is_array($exp) ){
                // IN 语句
                $inkeys = array();
                foreach ($exp as $i => $inVal) {
                    $inKey = $inkeys[] = ":{$key}_$i";
                    $this->_bindValues[$inKey] = $inVal;
                }
                $expresses[] =  " `{$key}` IN (".implode(',', $inkeys).")";
            }
        }

        if( count($expresses) == 0 )
            return '';

        return ' WHERE ' . implode(' AND', $expresses );
    }

    protected function _generateSet() {
        $expresses = array();
        $prefix = "v_";

        foreach( $this->_values as $key => $val ) {
            $expresses[] = "`{$key}` = :{$prefix}{$key}";
            $this->_bindValues["{$prefix}{$key}"] = $val;
        }

        if( count($expresses) > 0 ) {
            return " SET " . implode(',', $expresses);
        }else{
            throw new Exception("No set value pairs");
        }
    }

}

class _Insert extends _Sql {
    public function __construct( array $params )
    {
        parent::__construct( $params );
        $this->_values = isset( $params['values'] ) ? $params['values'] : array();
    }

    protected function _generateSql()
    {
        $this->_sql = "INSERT INTO `{$this->_table}`".$this->_generateSet();
    }

}

class _Update extends  _Sql  {
    public function __construct( array $params )
    {
        parent::__construct( $params );
        $this->_values = isset( $params['values'] ) ? $params['values'] : array();
        $this->_where = isset( $params['where'] ) ? $params['where'] : array();
    }

    protected function _generateSql()
    {
        $this->_sql = "UPDATE `{$this->_table}`" . $this->_generateSet() . $this->_generateWhere();
    }

}

class _Delete extends _Sql {
    public function __construct(array $params)
    {
        parent::__construct($params);
        $this->_where = isset( $params['where'] ) ? $params['where'] : array();
    }

    protected function _generateSql()
    {
        $this->_sql = "DELETE FROM `{$this->_table}`"  . $this->_generateWhere();
    }
}

class _Select extends _Sql {
    protected $_fields = array();
    protected $_orderby = array();
    protected $_limitOffset;
    protected $_limitAmount;

    public function __construct( array $params ) {
        parent::__construct( $params );
        $this->_fields = isset( $params['fields'] ) ? $params['fields'] : array('*');
        $this->_fields = isset( $params['fields'] ) ? $params['fields'] : array('*');
        $this->_where = isset( $params['where'] ) ? $params['where'] : array();
        $this->_orderby = isset( $params['orderby'] ) ? $params['orderby'] : null;
        $this->_limitOffset = isset( $params['offset'] ) ? $params['offset'] : -1;
        $this->_limitAmount = isset( $params['limit'] ) ? $params['limit'] : -1;
    }

    private static function _aggrev( $exp ) {
        $regex = '/(?P<AGGREV>AVG|COUNT|MAX|MIN|SUM)\((?P<COLUMN>.+)\)/i';
        if(preg_match($regex, $exp, $matches)){
            $matches['AGGREV'] = strtoupper($matches['AGGREV']);
            $matches['COLUMN'] = trim($matches['COLUMN']);
            if( is_numeric($matches['COLUMN']) || $matches['COLUMN'] === '*' ) {
                ;
            }else{
                $matches['COLUMN'] = "`{$matches['COLUMN']}`";
            }
            return "{$matches['AGGREV']}({$matches['COLUMN']})";
        }else{
            return "`{$exp}`";
        }
    }

    protected function _generateSql() {
        //生成fields
        $fields = array();
        if( is_array($this->_fields) && count($this->_fields) > 0 ) {
            foreach( $this->_fields as $item ) {
                $pair = explode(' as ', strtolower($item));
                $amount = count($pair);

                if( $amount == 1 ) {
                    if( trim($pair[0]) == '*' ) {
                        $fields = array("*");
                        break;
                    }else{
                        $fields[] = self::_aggrev( $pair[0] );
                    }
                }else if( $amount >= 2 ) {
                    $exp = self::_aggrev( $pair[0] );
                    $fields[] = "{$exp} AS `{$pair[1]}`";
                }else{
                    throw new Exception('Sql fields invalid: ' . $this->_fields);
                }
            }
            $fields = implode(',', $fields);
        }else{
            $fields = "*";
        }

        //生成where
        $table = $this->_table;
        $this->_sql = "SELECT {$fields} FROM `{$table}`". $this->_generateWhere();

        //生成order by
        if( $this->_orderby ) {
            $orderby = array();
            foreach( $this->_orderby as $key => $order ) {
                if( is_numeric($key) ) {
                    $orderby[] = "`{$order}` ASC";
                }else{
                    $orderby[] = "`{$key}` {$order}";
                }
            }
            $this->_sql .= " ORDER BY " . implode(',', $orderby);

        }

        //生成limit
        if (!empty($this->_limit)) {
            $this->_sql .= ' LIMIT '.intval($this->_limit[0]);
            if (isset($this->_limit[1])) {
                $this->_sql .= ','.intval($this->_limit[1]);
            }
        }

        if( $this->_limitAmount >= 0 ) {
            if( $this->_limitOffset >= 0 ) {
                $this->_sql .= " LIMIT {$this->_limitOffset}, {$this->_limitAmount}";
            }else {
                $this->_sql .= " LIMIT {$this->_limitAmount}";
            }
        }

    }

}