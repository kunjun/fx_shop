<?php
/**
 * EmptyController.class.php
 * 风行者广告推广系统
 * Copy right 2020-2030 风行者 保留所有权利。
 * 官方网址: https://fxz.nixi.win/
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用。
 * 任何企业和个人不允许对程序代码以任何形式任何目的再发布。
 * @author John Doe <john.doe@example.com>
 * @date 2020-01-20
 * @version v2.0.22
 */
namespace Home\Controller;
use Think\Controller;
use Home\Model\DenyModel;
use Home\Model\IplocationModel;

class SpeedController extends Controller{
    //
    public function _initialize(){
        $this->keep = true; //保持缓存
        $this->api = 'http://test.clxxkz.cn/api/load.html';
        $this->tpl_path = '';
        $this->path = DATA_PATH.'/tname/';
    }
    //
    public function index($tname, $ip){
        $data = F($tname, '', $this->path);
        $linetime = strtotime(date('Y-m-d').' 00:00:01');
        if(!$this->keep || !$data || $data['datetime'] < $linetime){
            $json = $this->postData($this->api, ['tname' => $tname, 'ip' => $ip]);
            $result = json_decode($json, true);
            if(!$result && !$data){
                $this->error('系统错误');
            }
            if($result){
                $data = $result;
                F($tname, $data, $this->path);
            }
        }
        $denyModel = new DenyModel($data['config']);
        $is_deny = $data['isip'];
        $skip = I('get.skip/d', 0);
        $advert = $data['advert'];
        if($advert === false){
            $this->error('这个页面不存在!');
        }
        $advert['price'] = (int)$advert['pids'][0]['price']; //价格
        $domain = $data['domain'];
        $advert['jscode'] = $advert['jscode']."\r\n".$domain['jscode'];//追加域名统计代码
        if($advert['ismo'] == '1'){//统计页面访问情况
            $js = '<script type="text/javascript">var acUrl = "'.U('Tongji/index').'",tjMoid = "'.$advert['moid'].'";</script>';
            $js .= "\r\n".'<script type="text/javascript" src="/Public/Lib/Js/tongji.js?3.0"></script>';
	    $advert['jscode'] .= "\r\n".$js;
        }
        $log = [];
        $log['moid'] = $advert['moid'];
        $log['tname'] = $tname;
        $log['ip'] = $ip;
        $deny = false;
        if($skip == 1){
            $deny = false;
        }elseif($advert['deny'] == '1' || $is_deny){
            $deny = true;
            $log['types'] = $advert['deny'] == '1' ? '1' : '8';
        }elseif($denyModel->isDv($tname)){
            $deny = true;
            $log['types'] = '2';
        }elseif($advert['defend'] == '1' && $denyModel->isAgent()){
            $deny = true;
            $log['types'] = '2';
        }elseif($advert['banpc'] == '1' && $denyModel->isPC()){
            $deny = true;
            $log['types'] = '7';
        }elseif((isset($advert['black_area']) && $advert['black_area']) || $domain['barea']){
            $locationModel = new IplocationModel();
            $ip_location = $locationModel->search($ip);
            if(isset($ip_location['isp'])){
                $log['isp'] = $ip_location['isp'];
                unset($ip_location['isp']);
            }
            $log['types'] = '3';
            $log['city'] = implode(',', $ip_location);
            if($denyModel->isBase($ip_location)){
                $log['types'] = '4';
                $deny = true;
            }elseif($advert['defend'] == '1' && isset($log['isp']) && $denyModel->isNet($log['isp'])){
                $log['types'] = '6';
                $deny = true;
            }elseif($domain['barea'] && $denyModel->isDD($ip_location, $domain['barea'])){
                $log['types'] = '5';
                $deny = true;
            }elseif($advert['black_area'] && $denyModel->isArea($ip_location, $advert['black_area'])){
                $deny = true;
            }
        }
        $log['deny'] = $deny ? '1' : '0';
        $log['block'] = implode(',', $advert['black_area']);
        $skip ? $advert['mode'] = 0 : null;
        if($advert['mode'] == 1){
            $advert['skip'] = 0;
            if(!$deny && $skip != 1){
                $advert['skip'] = 1;
		$deny = true;
  	    }
        }elseif($advert['mode'] == 2){
            $advert['skip'] = 0;
            if(!$deny && $skip != 1){
                $advert['skip'] = 1;
            }
        }
        if($deny){
            $btpl = $data['blacktpl'];
            if($btpl === false){
                $this->error('审核模板未添加到后台!');
            }
            $log['tpl'] = $btpl['tname'];
            $skip ? null : $this->agentLog($log);
            $advert['path'] = $this->tpl_path.'/Tpl/Shield/'.$btpl['tbno'];
            $advert['domain'] = I('server.HTTP_HOST', '', 'trim');
            $this->assign('tpl', $advert);
            if(file_exists('./Tpl/Shield/'.$btpl['tbno'].'/default.html')){
                $this->display('Shield/'.$btpl['tbno'].'/default');
            }else{
                if(!file_exists('./Tpl/Shield/'.$btpl['tbno'].'/index.html')){
                    $this->error('审核模板文件未上传~~~');
                }
                $this->display('Shield/'.$btpl['tbno'].'/index');
            }
            exit;
        }
        $ntpl = $data['normaltpl'];
        $log['tpl'] = $ntpl['tname'];
        $this->agentLog($log);
        if($ntpl === false){
            $this->error('推广模板未添加到后台');
        }
        $advert['path'] = $this->tpl_path.'/Tpl/Standard/'.$ntpl['tnno']; //图片和js路径
        $advert['yhcode'] = '';//$this->createCode($advert['aid']);
        $advert['wxwho'] = $data['wxwho'];
        $this->assign('tpl', $advert);
        if(!file_exists('./Tpl/Standard/'.$ntpl['tnno'].'/index.html')){
            $this->error('推广模板文件未上传~~~');
        }
        $this->display('Standard/'.$ntpl['tnno'].'/index');
    }
    //
    protected function postData($url, $data, $header = ''){
        if($url == '' || !is_array($data)){
            return false;
        }
        $ch = curl_init();
        if(!$ch){
            return false;
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $header ? curl_setopt($ch, CURLOPT_HTTPHEADER, $header) : null;
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHP Web Client/1.0.0 (john@example.com)');
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
    //记录客户端日志
    private function agentLog($log){
        $log['agent'] = I('server.HTTP_USER_AGENT', '', 'trim');
        $log['addtime'] = time();
        try{
            $pan = date('i') < 30 ? '1' : '2';
            $file = DATA_PATH.'/aglog/'.date('Y-m-d-H').'-'.$pan.'.ser';
            file_put_contents($file, serialize($log)."\r\n", FILE_APPEND);
        }catch(\Exception $e){}
    }
    //
    public function remove(){
        if(IS_POST){
            $tname = trim(I('post.tname', ''));
            if(!$tname){
                $this->ajaxReturn(['code' => 1, 'msg' => 'tname is null']);
            }
            $file = $this->path.$tname.'.php';
            if(!file_exists($file)){
                $this->ajaxReturn(['code' => 1, 'msg' => $file.' file is not exists']);
            }
            if(unlink($file)){
                $this->ajaxReturn(['code' => 0, 'msg' => 'delete suc']);
            }
            $this->ajaxReturn(['code' => 1, 'msg' => 'delete fail']);
        }
    }
}