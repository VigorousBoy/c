<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/7/24
 * Time: 14:10
 */
defined('BASEPATH') OR exit('No direct script access allowed');

class Receive extends CI_Controller
{

    public function receiveMsg(){
        if($_GET["echostr"])//验证签名
        {
           $this->valid();
        }else//其他事件
        {
            $this->responseMsg();
        }
    }
    /*
     * 验证服务器
     * */
    public function valid()
    {
        $echoStr = $_GET["echostr"];
        if($this->checkSignature()){
            echo $echoStr;
            exit;
        }
    }
    private function checkSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $token = $this->config->item('token');
        $tmpArr = array($token, $timestamp, $nonce);
        sort($tmpArr,SORT_STRING);
        $tmpStr = implode( $tmpArr );
        $tmpStr = sha1( $tmpStr );
        if( $tmpStr == $signature ){
            return true;
        }else{
            return false;
        }
    }
}