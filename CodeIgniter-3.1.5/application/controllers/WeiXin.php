<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/7/19
 * Time: 13:24
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class WeiXin extends CI_Controller
{
    private $appId='wx33b090e0a4bb0ea0';
    private $securet='947fd0db895e6da5261812b6e3792eea';


    /*
     * 获取access_token 和 jsapi_ticket
     * */
    public function getAccessToken(){

        if(file_exists('access_token.json')){
            $res = file_get_contents('access_token.json');
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
            echo $res;
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
            file_put_contents('access_token.json', json_encode(array('access_token'=>$access_token,'jsapi_ticket'=>$jsapi_ticket,'expires_time'=>$expires_time)));
            //对指定域名进行推送
            $push_url=$this->config->item('push_url');
            if($push_url){
                foreach ($push_url as $v){
                    $this->http_request($v,json_encode(array('access_token'=>$access_token,'jsapi_ticket'=>$jsapi_ticket)));
                }
            }
        }
        return json_encode(array('access_token'=>$access_token,'jsapi_ticket'=>$jsapi_ticket));
    }

    /*
     * snsapi_base获取openid
     * */
    public function getOpenid(){
        $appId=$this->appId;
        $this->load->helper('url');
        $redirect_uri=urlencode(site_url('WeiXin/getOpenid'));
        $url='https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$appId.'&redirect_uri='.$redirect_uri.'&response_type=code&scope=snsapi_base&state=123#wechat_redirect';
        $this->http_request($url);
    }

    public function authorize1(){
        $code=$_GET['code'];
        echo 123;
    }
    /*
     *snsapi_userinfo 获取用户信息
     * */


    /*
     * 自定义菜单
     * */
    public function createMenu()
    {
        $menu=file_get_contents("php://input");
        $access_token=$this->getAccessToken();
        $access_token=json_decode($access_token,true);
        $access_token=$access_token['access_token'];
        $url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=$access_token";
        if (!empty($menu)) {
            $res = $this->http_request($url,$menu);
            return $res;
        }
    }
    /*
     * 测试微信自定义菜单
     * */
    public function test()
    {
        $json = '{
     "button":[
     {    
          "type":"click",
          "name":"今日歌曲",
          "key":"V1001_TODAY_MUSIC"
      },
      {
           "name":"菜",
           "sub_button":[
           {    
               "type":"view",
               "name":"搜索",
               "url":"http://www.soso.com/"
            },
            {
                 "type":"miniprogram",
                 "name":"wxa",
                 "url":"http://mp.weixin.qq.com",
                 "appid":"wx286b93c14bbf93aa",
                 "pagepath":"pages/lunar/index"
             },
            {
               "type":"click",
               "name":"赞一下我们",
               "key":"V1001_GOOD"
            }]
       }]
 }';
        $url='http://localhost/CodeIgniter-3.1.5/index.php/WeiXin/createMenu';
        $res = $this->http_request($url,$json);
        var_dump($res);
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

    public function doRequest($host,$path, $param=array()){
        $query = isset($param)? http_build_query($param) : '';

        $port = 80;
        $errno = 0;
        $errstr = '';
        $timeout = 10;

        $fp = fsockopen($host, $port, $errno, $errstr, $timeout);

        $out = "POST ".$path." HTTP/1.1\r\n";
        $out .= "host:".$host."\r\n";
        $out .= "content-length:".strlen($query)."\r\n";
        $out .= "content-type:application/x-www-form-urlencoded\r\n";
        $out .= "connection:close\r\n\r\n";
        $out .= $query;

        fputs($fp, $out);
        fclose($fp);
    }
    function get_url() {
        $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
        $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
        $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
        $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : $path_info);
        return $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '').$relate_url;
    }
}