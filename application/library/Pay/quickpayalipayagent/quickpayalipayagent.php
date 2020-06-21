<?php
/**
 * File: quickpayalipayagent.php
 * Functionality: QuickPay-支付宝-快捷版
 * Author: Cheeli
 * WebSite: http://www.cheeli.com.cn
 * Date: 2020-06-11
 */
namespace Pay\quickpayalipayagent;
use \Pay\notify;

class quickpayalipayagent
{
 	private $paymethod = "quickpayalipayagent";
	
	//处理请求
	public function pay($payconfig,$params)
	{
		$amount = (float)$params['money'] * 100.00 ;  
		$config = array(
			"appid" =>   $payconfig['app_id'],//平台ID号 
			"timestamp" => time()  , 
			"ext_trade_no" =>   $params['orderid']  ,  
			"amount" => $amount  , //原价,单位为分 
			"attach" =>  $params['attach'], //自定义参数		
			"body" => $params['productname'] ,  
			"notify_url" => $params['weburl'] . '/product/notify/?paymethod='.$this->paymethod,
			"agent_channel" =>  '2'  ,  // 2： 支付宝快捷收款
 
		);
		
		try{
			 
		    $apiHost = $payconfig['configure3'];
			$sign = $this->_signParams($config, $payconfig['app_secret']);
			$config['sign'] = $sign ;
			$curl_data =  $this->_curlPost( $apiHost,$config); 
			$response = json_decode($curl_data,true);
			if(is_array($response)){
				if($response['code'] != 0){
					return array('code'=>1002,'msg'=>$response['message'],'data'=>'' );
				}else{
					$qr =   $response['data']['qr_url'];

					$money = $params['money'];
					//计算关闭时间
					$closetime = 180;
					$result = array('type'=>0,'subjump'=>0,'subjumpurl'=>'','paymethod'=>$this->paymethod,'qr'=>$qr,'payname'=>$payconfig['payname'],'overtime'=>$closetime,'money'=>$money);
					return array('code'=>1,'msg'=>'success','data'=>$result);
				}
			}else{
				return array('code'=>1001,'msg'=>"支付接口请求失败",'data'=>'');
			}
		} catch (PayException $e) {
			return array('code'=>1000,'msg'=>$e->errorMessage(),'data'=>'');
		} catch (\Exception $e) {
			return array('code'=>1000,'msg'=>$e->getMessage(),'data'=>'');
		}
	}
	
	
	//处理返回
	public function notify($payconfig)
	{
	  
		$POST_input = file_get_contents('php://input');
		$params = json_decode($POST_input, true); 
		// return 'error|Notify: auth fail, data:' . var_dump ( $params)  ; 
		$dataMap = array(
			"code" =>   $params['code'], 
			"message" =>   $params['message']  ,  
			"action" =>   $params['action'] ,  
			"data" => $params['data'] ,  
			"timestamp" =>    $params['timestamp'] ,  
		);
		$serverSign = $params['sign'];
        $sign = $this->_signParams($dataMap, $payconfig['app_secret']);     
		 
		if ($serverSign != $sign) { //不合法的数据 KEY密钥为你的密钥
			return 'error|Notify: auth fail';
		} else { //合法的数据
			//业务处理
			$data =  json_decode($params['data'],true);
			$fee = (float)$data['total_fee']   ;
			$out_trade_no = $data['out_trade_no'];
			$quickpay_trade_no = $data['quickpay_trade_no'];
			$totalFeeYuan =  number_format($fee /100 , 2); 
			// return 'error|Notify: out_trade_no:'. $out_trade_no . "quickpay_trade_no:" . $quickpay_trade_no . " totalFeeYuan:" . $totalFeeYuan ;
			$config = array('paymethod'=>$this->paymethod,'tradeid'=>quickpay_trade_no ,'paymoney'=>$totalFeeYuan, 'orderid'=>$out_trade_no );

			$notify = new \Pay\notify();
			$data = $notify->run($config);
			if($data['code']>1){
				return 'error|Notify: '.$data['msg'];
			}else{
				return 'success';
			}
		}
	}
	
 
private function _curlPost($url,$params){
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL,$url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT,300); //设置超时
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
	$result = curl_exec($ch);
	curl_close($ch);
	return $result;	
}

private function _signParams($params,$secret){
	$sign = $signstr = "";
	if(!empty($params)){
		ksort($params);
		reset($params);
		
		foreach ($params AS $key => $val) {
			if ($key == 'sign') continue; 
			$signstr .= $key.$val;
		}
		$sign = md5($secret.$signstr.$secret);
	}
	return $sign;
}	
	 
}
