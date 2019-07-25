<?php
/**
 * Created by PhpStorm.
 * User: zhoujie
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
    public function transmitText($object, $content)
    {
        $textTpl = "<xml>
					<ToUserName><![CDATA[%s]]></ToUserName>
					<FromUserName><![CDATA[%s]]></FromUserName>
					<CreateTime>%s</CreateTime>
					<MsgType><![CDATA[text]]></MsgType>
					<Content><![CDATA[%s]]></Content>
					<FuncFlag>0</FuncFlag>
					</xml>";
        $result = sprintf($textTpl, $object->FromUserName, $object->ToUserName, time(), $content);
        return $result;
    }
    public function handleEvent($object)
    {
        $contentStr = "";
        switch ($object->Event)
        {
            case "subscribe":
            case "unsubscribe":
            case "CLICK":
            case "SCAN":
                break;
            case "card_pass_check"://卡券审核通过事件
            case "card_not_pass_check"://卡券审核不通过事件
                $str='<xml>
                    <ToUserName><![CDATA[%s]]></ToUserName>
                    <FromUserName><![CDATA[%s]]></FromUserName>
                    <CreateTime>%s</CreateTime>
                    <MsgType><![CDATA[event]]></MsgType>
                    <Event><![CDATA[%s]]></Event> //不通过为card_not_pass_check
                    <CardId><![CDATA[%s]]></CardId>
                    <RefuseReason><![CDATA[%s]]></RefuseReason> 
                 </xml>';
                $str=sprintf($str,$object->ToUserName,$object->FromUserName,$object->CreateTime,$object->Event,$object->CardId,$object->RefuseReason);
                $this->http_request('http://111.67.199.76/index.php',$str);
                break;
            case "user_get_card"://卡券领取事件
                break;
            case "user_pay_from_pay_cell"://卡券买单事件
                break;
            default :
                $contentStr = "Unknow Event: ".$object->Event;
                break;
        }
        return $contentStr;
    }
    public function http_request($url, $data = null)
    {
        $header[] = "Content-type: text/xml";//定义content-type为xml
        $ch = curl_init(); //初始化curl
        curl_setopt($ch, CURLOPT_URL, $url);//设置链接
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//设置是否返回信息
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);//设置HTTP头
        curl_setopt($ch, CURLOPT_POST, 1);//设置为POST方式
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);//POST数据
        $response = curl_exec($ch);//接收返回信息
        if(curl_errno($ch)){//出错则显示错误信息
            print curl_error($ch);
        }
        curl_close($ch); //关闭curl链接
        return $response;//显示返回信息
    }
}