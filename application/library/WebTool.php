<?php

/**
 * Created by PhpStorm.
 * User: shaolei
 * Date: 16/9/8
 * Time: 下午4:34
 */
class WebTool
{
    public static function fromPostJSON() {
        $postStr = file_get_contents('php://input');
        return json_decode( $postStr, true );
    }

    public static function toRespJSON( $data=NULL, $ecode='0', $emsg='ok' ) {
        $resp = array(
            'ecode'=>strval( $ecode ),
            'emsg'=>$emsg,
            'data'=>$data,
        );
        return json_encode( $resp );
    }
}