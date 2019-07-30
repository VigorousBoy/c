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
            $jsapi_ticket=$result["jsapi_ticket"];
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
                $this->load->helper('url');
                $push_url=site_url('WeiXin/urlPush');
                $this->asyncPost($push_url);
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
       if($third_uri && $re['openid']){
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
        if($third_uri && $openid){
            if(stripos($third_uri,'?')){
                $third_uri=str_replace('?','?openid='.$openid.'&access_token='.$access_token.'&',$third_uri);
            }else{
                $third_uri.='?openid='.$openid.'&access_token='.$access_token;
            }
            header("Location:".$third_uri);
        }
    }

    public function getUserDetail(){
        $openid=$_GET['openid'];
        $access_token=$_GET['access_token'];
        $url='https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN';
        echo $this->http_request($url);
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
    public function do_upload(){
        $this->load->helper("form");
        $config['upload_path']='./uploads/';
        $config['allowed_types']='jpg|png';
        $config['max_size']=1024;
        $config['max_width']=300;
        $config['max_height']=300;
        $this->load->library('upload',$config);
        if(!$this->upload->do_upload('buffer')){
            $error=array('res'=>0,'error'=>$this->upload->display_errors());
            return $error;
        }else{
            $data=array('res'=>1,'upload_data'=>$this->upload->data());
            return $data;
        }
    }
    /*
     * 上传卡券logo
     * */
    public function cardLogo(){
        $access_token=$this->getAccessToken(true);
        $access_token=json_decode($access_token,true);
        $access_token=$access_token['access_token'];
        $result=$this->do_upload();
        if($result['res']){
            $target=$result['upload_data']['full_path'];
            $url = "https://api.weixin.qq.com/cgi-bin/media/uploadimg?access_token=".$access_token.'&type=image';
            if (class_exists('CURLFile')) {
                $file = array("buffer"=>new CURLFile(realpath($target)),'access_token'=>$access_token);  //$target即为logo图片路径
            } else {
                $file = array("buffer"=>'@'.realpath($target),'access_token'=>$access_token);  //$target即为logo图片路径
            }
            echo $this->http_request($url,$file);
        }else{
            echo json_encode(array("errcode"=>100000,"errmsg"=>$result['error']));
        }
    }
    /*
     * 生成卡券
     * */
    public function createCard(){
        $access_token=$this->getAccessToken(true);
        $access_token=json_decode($access_token,true);
        $access_token=$access_token['access_token'];
        $card=file_get_contents("php://input");
        $url='https://api.weixin.qq.com/card/create?access_token='.$access_token;
        echo $this->http_request($url,$card);
    }
    /*
     * 设置买单接口
     * */
    public function payCell(){
        $access_token=$this->getAccessToken(true);
        $access_token=json_decode($access_token,true);
        $access_token=$access_token['access_token'];
        $data=file_get_contents("php://input");
        $url='https://api.weixin.qq.com/card/paycell/set?access_token='.$access_token;
        echo $this->http_request($url,$data);
    }
    /*
     * 自助核销接口
     * */
    public function selfConsumeCell(){
        $access_token=$this->getAccessToken(true);
        $access_token=json_decode($access_token,true);
        $access_token=$access_token['access_token'];
        $data=file_get_contents("php://input");
        $url='https://api.weixin.qq.com/card/selfconsumecell/set?access_token='.$access_token;
        echo $this->http_request($url,$data);
    }
    /*
     * 创建二维码接口
     * */
    public function qrCodeCreate(){
        $access_token=$this->getAccessToken(true);
        $access_token=json_decode($access_token,true);
        $access_token=$access_token['access_token'];
        $data=file_get_contents("php://input");
        $url='https://api.weixin.qq.com/card/qrcode/create?access_token='.$access_token;
        echo $this->http_request($url,$data);
    }

    /*
     * 核销卡券
     * */
    //查询code接口
    public function codeCheck(){
        $access_token=$this->getAccessToken(true);
        $access_token=json_decode($access_token,true);
        $access_token=$access_token['access_token'];
        $data=file_get_contents("php://input");
        $url='https://api.weixin.qq.com/card/code/get?access_token='.$access_token;
        echo $this->http_request($url,$data);
    }
    /*
     * 线下核销
     * */
    public function cardConsume(){
        $access_token=$this->getAccessToken(true);
        $access_token=json_decode($access_token,true);
        $access_token=$access_token['access_token'];
        $data=file_get_contents("php://input");
        $url='https://api.weixin.qq.com/card/code/consume?access_token='.$access_token;
        echo $this->http_request($url,$data);
    }

    /*
     * Code解码接口
     * */
    public function codeDecrypt(){
        $access_token=$this->getAccessToken(true);
        $access_token=json_decode($access_token,true);
        $access_token=$access_token['access_token'];
        $data=file_get_contents("php://input");
        $url='https://api.weixin.qq.com/card/code/decrypt?access_token='.$access_token;
        echo $this->http_request($url,$data);
    }

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

    public function urlPush(){
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
        if(time()>($expires_time + $refresh_time) || !$access_token || !$jsapi_ticket){
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
        }
        //进行推送
        $push_url=$this->config->item('push_url');
        if($push_url){
            foreach ($push_url as $v){
                $push_data=json_encode(array('access_token'=>$access_token,'jsapi_ticket'=>$jsapi_ticket));
                $this->http_request($v,$push_data);
                //进行推送log的记录
                $file= $file=ROOTPATH.'push_log.txt';
                $str='地址：'.$v."\r\n".'时间：'.date('Y-m-d H:i:s')."\r\n".'数据：'.$push_data."\r\n";
                file_put_contents($file,$str,FILE_APPEND);
            }
        }

    }
    public function http_request($url, $data = null)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, 1);
//            curl_setopt($curl, CURLOPT_TIMEOUT, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    /*
     * 忽略返回值的请求
     * */
    public function asyncPost($url)
    {
        $args = parse_url($url); //对url做下简单处理
        $host = $args['host']; //获取上报域名
        $path = $args['path'];//获取上报地址
        $fp = fsockopen($host, 80, $error_code, $error_msg, 1);
        if($fp){
            stream_set_blocking($fp, true);//开启了手册上说的非阻塞模式
            stream_set_timeout($fp, 1);//设置超时
            $header = "GET $path HTTP/1.1\r\n"; //注意 GET/POST请求都行 我们需要自己按照要求拼装Header http协议遵循1.1
            $header .= "Host: $host\r\n";
            $header .= "Connection: close\r\n\r\n";//长连接关闭
            fputs($fp, $header);
            fclose($fp);
        }
    }
}