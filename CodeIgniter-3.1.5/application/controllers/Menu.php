<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/7/25
 * Time: 17:35
 */
defined('BASEPATH') OR exit('No direct script access allowed');
class Menu extends CI_Controller
{

    public function index(){
        $this->load->helper('url');
        $this->load->view('menu/index.php');
    }
}