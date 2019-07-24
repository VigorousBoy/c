<?php
/**
 * Created by PhpStorm.
 * User: zhoujie
 * Date: 2019/7/19
 * Time: 13:24
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class WeiXin extends CI_Controller
{
    private $appId='wx33b090e0a4bb0ea0';
    private $securet='947fd0db895e6da5261812b6e3792eea';
    private $openid='changanmazida_openid';

    /*
     * 获取access_token 和 jsapi_ticket
     * */
    public function getAccessToken($inner=false){
        $file=ROOTPATH.'access_token.json';
        if(file_exists($file)){
            $res = file_get_contents($file);
            $result = json_decode($res,true);
            $expires_time = $result["expires_time"];
            $access_token = $result["access_token"];
            $jsapi_ticket=$result["access_token"];
        }else{
            $expires_time =0;
            $access_token ='';
            $jsapi_ticket='';
        }
        $refresh_time=$this->config->item('refresh_time');
        if (time()>($expires_time + $refresh_time) || !$access_token || !$jsapi_ticket){
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$this->appId."&secret=".$this->securet;
            $res = $this->http_request($url);
            $result = json_decode($res, true);
            $access_token = $result["access_token"];
            //获取jsapi_ticket
            $url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=' . $access_token;
            $res =$this->http_request($url);
            if (!empty($res)){
                $result = json_decode($res, true);
                $jsApiTicke =$result['ticket'];
                if (!empty($jsApiTicke)) {
                    $jsapi_ticket=$jsApiTicke;
                }
            }
            $expires_time = time();
            file_put_contents($file, json_encode(array('access_token'=>$access_token,'jsapi_ticket'=>$jsapi_ticket,'expires_time'=>$expires_time)));
            //对指定域名进行推送
            $push_url=$this->config->item('push_url');
            if($push_url){
                foreach ($push_url as $v){
                    $this->http_request($v,json_encode(array('access_token'=>$access_token,'jsapi_ticket'=>$jsapi_ticket)));
                }
            }
        }
        if($inner){
            return json_encode(array('access_token'=>$access_token,'jsapi_ticket'=>$jsapi_ticket));
        }else{
            echo json_encode(array('access_token'=>$access_token,'jsapi_ticket'=>$jsapi_ticket));
        }
    }

    /*
     * snsapi_base获取openid
     * */
    public function getOpenid(){
        $third_uri=isset($_GET['redirect_uri'])?$_GET['redirect_uri']:'';
        if($_COOKIE[$this->openid]){
            if($third_uri){
                if(stripos($third_uri,'?')){
                    $third_uri=str_replace('?','?openid='.$_COOKIE[$this->openid].'&',$third_uri);
                }else{
                    $third_uri.='?openid='.$_COOKIE[$this->openid];
                }
                header("Location:".$third_uri);
            }
        }else{
            if($third_uri){
                $third_uri=base64_encode($third_uri);
            }
            $appId=$this->appId;
            $this->load->helper('url');
            $redirect_uri=urlencode(site_url('WeiXin/authorize1').'?s=123');
            $url='https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$appId.'&redirect_uri='.$redirect_uri.'&response_type=code&scope=snsapi_base&state='.$third_uri.'&connect_redirect=1#wechat_redirect';
            header("Location:".$url);
        }
    }

    public function authorize1(){
       $appId=$this->appId;
       $securet=$this->securet;
       $state=$_GET['state'];
       $third_uri='';
       if($state){
           $third_uri=base64_decode($state);
       }
       $code=$_GET['code'];
       $url='https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$appId.'&secret='.$securet.'&code='.$code.'&grant_type=authorization_code';
       $re=$this->http_request($url);
       $re=json_decode($re,true);
       //获取当前域名
       setcookie($this->openid,$re['openid'],time()+3600*24*365,'/',$_SERVER['HTTP_HOST']);
       if($third_uri){
           if(stripos($third_uri,'?')){
               $third_uri=str_replace('?','?openid='.$re['openid'].'&',$third_uri);
           }else{
               $third_uri.='?openid='.$re['openid'];
           }
           header("Location:".$third_uri);
       }
    }
    /*
     *snsapi_userinfo 获取用户信息
     * */
    public function getUserInfo(){
        //用户授权
        $third_uri=isset($_GET['redirect_uri'])?$_GET['redirect_uri']:'';
        if($third_uri){
            $third_uri=base64_encode($third_uri);
        }
        $appId=$this->appId;
        $this->load->helper('url');
        $redirect_uri=urlencode(site_url('WeiXin/authorize2'));
        $url='https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$appId.'&redirect_uri='.$redirect_uri.'&response_type=code&scope=snsapi_userinfo&state='.$third_uri.'&connect_redirect=1#wechat_redirect';
        header("Location:".$url);
    }
    public function authorize2(){
        $appId=$this->appId;
        $securet=$this->securet;
        $state=$_GET['state'];
        $third_uri='';
        if($state){
            $third_uri=base64_decode($state);
        }
        $code=$_GET['code'];
        $url='https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$appId.'&secret='.$securet.'&code='.$code.'&grant_type=authorization_code';
        $re=$this->http_request($url);
        $re=json_decode($re,true);
        $access_token=$re['access_token'];
        $openid=$re['openid'];
        setcookie($this->openid,$re['openid'],time()+3600*24*365,'/',$_SERVER['HTTP_HOST']);
        //通过access_token获取用户信息
        $userinfo=$this->getUserDtail($openid,$access_token);
        $userinfo=json_decode($userinfo,true);
        if($third_uri){
            if(stripos($third_uri,'?')){
                $third_uri=str_replace('?','?openid='.$userinfo['openid'].'&'.'nickname='.$userinfo['nickname'].'&',$third_uri);
            }else{
                $third_uri.='?openid='.$userinfo['openid'].'&'.'nickname='.$userinfo['nickname'];
            }
            header("Location:".$third_uri);
        }
    }

    public function getUserDtail($openid,$access_token){
        $url='https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN';
        return $this->http_request($url);
    }

    /*
     * 客服消息发送
     * */
    function customSendMsg(){
        $data=file_get_contents("php://input");
        $access_token=$this->getAccessToken(true);
        $access_token=json_decode($access_token,true);
        $access_token=$access_token['access_token'];
        $url='https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token='.$access_token;
        echo $this->http_request($url,$data);
    }

    //获取所有客服
    public function getKfAll(){
        $access_token=$this->getAccessToken(true);
        $access_token=json_decode($access_token,true);
        $access_token=$access_token['access_token'];
        $url='https://api.weixin.qq.com/cgi-bin/customservice/getkflist?access_token='.$access_token;
        echo $this->http_request($url);
    }
    //添加客服/
    public function addKf(){
        $data=file_get_contents("php://input");
        $access_token=$this->getAccessToken(true);
        $access_token=json_decode($access_token,true);
        $access_token=$access_token['access_token'];
        $url='https://api.weixin.qq.com/customservice/kfaccount/add?access_token='.$access_token;
        echo $this->http_request($url,$data);
    }
    //邀请客服绑定
    public function inviteWorker(){
        $data=file_get_contents("php://input");
        $access_token=$this->getAccessToken(true);
        $access_token=json_decode($access_token,true);
        $access_token=$access_token['access_token'];
        $url='https://api.weixin.qq.com/customservice/kfaccount/inviteworker?access_token='.$access_token;
        echo $this->http_request($url,$data);
    }

    /*
     * 消息的转发
     * */


    /*
     * 自定义菜单
     * */
    public function createMenu()
    {
        $menu=file_get_contents("php://input");
        $access_token=$this->getAccessToken(true);
        $access_token=json_decode($access_token,true);
        $access_token=$access_token['access_token'];
        $url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=$access_token";
        if (!empty($menu)) {
            $res = $this->http_request($url,$menu);
            echo $res;
        }
    }
    /*
     * 上传卡券logo
     * */
    public function cardLogo(){
        $access_token=$this->getAccessToken(true);
        $access_token=json_decode($access_token,true);
        $access_token=$access_token['access_token'];
        $target=$_GET['target'];
        $url = "https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token=" . urlencode($access_token);
        $file = array("buffer"=>'@'.$target);  //$target即为logo图片路径
        echo $this->http_request($url,$file);
    }
    /*
     * 生成卡券
     * */
    public function createCard(){

    }
    /*
     * 核销卡券
     * */

    /*
     * json_encode 中文变Unicode的问题
     * */
    public function url_encode($str)
    {
        if (is_array($str)) {
            foreach ($str as $key => $value) {
                $str[urlencode($key)] = $this->url_encode($value);
            }
        } else {
            $str = urlencode($str);
        }

        return $str;
    }

    public function http_request($url, $data = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_TIMEOUT, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }
}