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
    public function responseMsg()
    {
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        if (!empty($postStr))
        {
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $RX_TYPE = trim($postObj->MsgType);

            switch($RX_TYPE)
            {
                case "text":
                case "voice":
                case "image":
                     $resultStr = $this->handleUserInput($postObj);
                     break;
                case "event":
                    $resultStr = $this->handleEvent($postObj);
                    break;
                default:
                    $resultStr = "Unknow msg type: ".$RX_TYPE;
                    break;
            }
            echo $resultStr;
        }else
        {
            echo "error";
            exit;
        }
    }
    public function handleUserInput($postObj){
        //所有的消息转交给客服
        $textTpl = " <xml> 
                    <ToUserName><![CDATA[%s]]></ToUserName>  
                    <FromUserName><![CDATA[%s]]></FromUserName>  
                    <CreateTime>%s</CreateTime>  
                    <MsgType><![CDATA[transfer_customer_service]]></MsgType> 
                    </xml>";
        $result = sprintf($textTpl, $postObj->FromUserName, $postObj->ToUserName,time());
        return $result;
    }
}