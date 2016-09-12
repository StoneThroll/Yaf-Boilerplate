<?php

/**
 * @name IndexController
 * @author root
 * @desc 默认控制器,后台管理页面框架
 * @see http://www.php.net/manual/en/class.yaf-controller-abstract.php
 */
class IndexController extends Yaf_Controller_Abstract
{

    /**
     * 默认动作
     */
    public function indexAction()
    {

        $config = Yaf_Application::app()->getConfig();

        //session使用实例
        $session = Yaf_Session::getInstance();
        $session->set('name', 'sl');
        $r = $session->get('name');
//        var_dump($r);

        //redis使用实例
        $redisCfg = $config->redis;
        $redis = new Redis();
        $redis->connect($redisCfg['host'], $redisCfg['port']);
        $redis->auth($redisCfg['passwd']);
        $redis->set('TEST:name', 'ls');
        $r = $redis->get('TEST:name');
//        var_dump($r);

        //MYSQL DAO使用实例
        //直接使用PDO对象
        $sql = "select * from teauser limit 2";
        $dao = Dao::db();
        $stmt = $dao->pdo()->prepare( $sql );
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        var_dump( $result );

        //单表Select
        echo "<br>SELECT==========================><br>";
        $dao->select(array(
            'table' => 'teauser',
            'fields' => array('user_name as name'),
            'where' => array('workshopid' => 1, "membertype"=>array(1,2)),
            'orderby' => array('name' => 'desc'),
            'offset' => 0,
            'limit' => 10,
        ));

        echo $dao->queryString();
        var_dump( $dao->bindValues() );
        var_dump( $dao->lastResult() );
        var_dump( $dao->fetchAll() );

        //直接获取行数
        echo "<br>Fetch Column==========================><br>";
        $dao->select(array(
            'table' => 'teauser',
            'fields' => array('COUNT(*)'),
        ));

        var_dump( $dao->queryString() );
        var_dump( $dao->lastResult() );
        var_dump( $dao->fetchColumn() );

        echo "<br>Insert==========================><br>";
        //Insert
        $dao->insert(array(
            'table' => 'teauser',
            'values' => array(
                'user_name'=>'sl_temp',
                'user_pwd'=>md5('123456'),
                'workshopid'=>'k',
                'SalesId'=>'3',
                'membertype'=>'1',
                'addtime'=>time(),
            ),
        ));

        var_dump( $dao->queryString() );
        var_dump( $dao->bindValues() );
        var_dump( $dao->lastResult() );
        var_dump( $dao->lastInsertId() );

        $lastid = $dao->lastInsertId();

        //Update
        echo "<br>Update==========================><br>";
        $dao->update(array(
            'table' => 'teauser',
            'values' => array(
                'user_pwd'=>md5('123456'),
                'workshopid'=>'1',
                'addtime'=>time(),
            ),
            'where' => array(
                'user_name'=>'sl_temp',
            )
        ));

        var_dump( $dao->queryString() );
        var_dump( $dao->bindValues() );
        var_dump( $dao->lastResult() );

        //Delete
        echo "<br>Delete==========================><br>";
        $dao->delete(array(
            'table' => 'teauser',
            'where' => array(
                'id' => $lastid,
            )
        ));

        var_dump( $dao->queryString() );
        var_dump( $dao->bindValues() );
        var_dump( $dao->lastResult() );


        //事务处理
        $dao->start();
        echo "<br>事务处理: Insert==========================><br>";
        //Insert
        $dao->insert(array(
            'table' => 'teauser',
            'values' => array(
                'user_name'=>'sl_temp',
                'user_pwd'=>md5('123456'),
                'workshopid'=>'k',
                'SalesId'=>'3',
                'membertype'=>'1',
                'addtime'=>time(),
            ),
        ));

        var_dump( $dao->queryString() );
        var_dump( $dao->bindValues() );
        var_dump( $dao->lastResult() );
        var_dump( $dao->lastInsertId() );

        echo "<br>事务处理: 提交==========================><br>";
        $dao->commit();
//        $dao->rollback();
        var_dump( $dao->lastResult() );

        echo 'how are you!';
        $this->getView()->assign("emc", 'aaaa');
        return true;
    }
}
