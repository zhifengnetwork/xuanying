<?php
/**
 * 萱莹集团
 * ============================================================================
 *   分销、代理
 */

namespace app\common\logic;

use app\common\logic\LevelLogic;
use think\Model;
use think\Db;

/**
 * 活动逻辑类
 */
class BonusLogic extends Model
{
	private $userId;//用户id
	private $goodId;//商品id
	private $goodNum;//商品数量
	private $orderSn;//订单编号
	private $orderId;//订单id
	private $catId;//商品分类
	private $zk;//商品折扣

	public function __construct($userId,  $goodId, $goodNum, $orderSn, $orderId, $catId=0,$zk=10)
	{	
		$this->userId = $userId;
		$this->goodId = $goodId;
		$this->goodNum = $goodNum;
		$this->orderSn = $orderSn;
		$this->orderId = $orderId;
		$this->catId = $catId;
		$this->zk = $zk;
	}


	public function bonusModel()
	{
		//$price = M('goods')->where(['goods_id'=>$this->goodId])->value('shop_price');
		//判断商品是否是分销商品或者代理商品
		$good = M('goods')
				->where('goods_id', $this->goodId)
				->field('is_distribut,is_agent,shop_price')
                ->find();
		if(($good['is_distribut'] == 1) && ($good['is_agent'] == 1)){
			$this->change_role(1,1);
			$dist = $this->distribution();
			$agent = $this->theAgent($this->userId);
			$this->sel($this->userId,$good['shop_price']);
			return true;
		}else if($good['is_distribut'] == 1){
			$this->change_role(1,0);
			$dist = $this->distribution();
			return true;
		}else if($good['is_agent'] == 1){
			$this->change_role(1,1);
			$agent = $this->theAgent($this->userId);
			$this->sel($this->userId,$good['shop_price']);
			return true;
		}else{
            return false;
		}	
	}

	/**
	* 更改身份
	*/
	public function change_role($distributor,$agent)
	{
	    $data = [];
	    if($distributor){
	        $data['is_distribut'] = $distributor;
        }
        if($agent){
            $data['is_agent'] = $agent;
        }
        if($data){
            M('users')->where('user_id',$this->userId)->update($data);
        }
	}

	/**
	* 分销模式
	**/
	public function distribution()
	{
        $distributor = $this->users($this->userId);
        // if ($distributor['is_distribut'] != 1) {
        // 	M('users')->where('user_id',$this->userId)->update(['is_distribut'=>1]);
        // }
		//判断上级用户是否为分销商
        if (!$distributor['first_leader']){
        	return false;
        }
		$goods = $this->goods();
		if($this->catId == C('customize.gift_goods_cat25')){
			$goods['shop_price'] = floor($goods['shop_price'] * $this->zk)/10;
			$commission_rate = M('goods_commission')->field('lev1 as commission_rate,lev2 as commission_rate2,type')->find($this->goodId);
		}elseif($this->catId == C('customize.gift_goods_cat'))
			return false;
		else{
			$commission_rate = M('goods_category')->field('commission_rate,commission_rate2')->find($goods['cat_id']);
			$commission_rate['type'] = 1;
		}
		$leader_level = M('Users')->where(['user_id'=>$distributor['first_leader']])->value('level');
		if($leader_level){
			$rate = $commission_rate['commission_rate'] * $this->goodNum;
			$commission = ($commission_rate['type'] == 1) ? ($goods['shop_price'] * ($rate / 100)) : $rate; //计算佣金
			$bool = M('users')->where(['user_id'=>$distributor['first_leader']])->setInc('user_money',$commission);
		}
		//查询上级的上级
		$leader_leader = M('Users')->where(['user_id'=>$distributor['first_leader']])->value('first_leader');
		$leader_leader_level = M('Users')->where(['user_id'=>$leader_leader])->value('level');
		if($leader_leader_level){
			$rate2 = $commission_rate['commission_rate2'] * $this->goodNum;
			$commission2 = ($commission_rate['type'] == 1) ? ($goods['shop_price'] * ($rate2 / 100)) : $rate2; //计算佣金
			$bool2 = M('users')->where(['user_id'=>$leader_leader])->setInc('user_money',$commission2);
		}

		$desc = "分销所得佣金";
		if($commission && $leader_level)$this->writeLog($distributor['first_leader'],$commission,$desc,102); //写入日志
		if($commission2 && $leader_leader && $leader_leader_level)$this->writeLog($leader_leader,$commission2,$desc,102); //写入日志
		return true;
	}
	//记录日志
	public function writeLog($userId,$money,$desc,$states)
	{
		$data = array(
			'user_id'=>$userId,
			'user_money'=>$money,
			'change_time'=>time(),
			'desc'=>$desc,
			'order_sn'=>$this->orderSn,
			'order_id'=>$this->orderId,
			'states'=>$states
		);
		$bool = M('account_log')->insert($data);
		if($bool){
			//分钱记录
			$data = array(
				'order_id'=>$this->orderId,
				'user_id'=>$userId,
				'status'=>1,
				'goods_id'=>$this->goodId,
				'money'=>$money,
				'states'=>$states
			);
			M('order_divide')->add($data);
            //agent_performance_log($userId, $money, $this->orderId);
		}
		return $bool;
	}

	//商品信息
	public function goods(){
		$goods = M('goods')->field("shop_price,cat_id")->where(['goods_id'=>$this->goodId])->find();
		return $goods;
	}

	//查询用户信息
	public function users($user_id){

		$users = M('users')->where(['user_id'=>$user_id])->find();
		return $users;
	}

	//查询用户信息
	public function first_leader($user_id){

		$users = M('users')->where(['user_id'=>$user_id])->find();
		return $users;
	}

	/**
	* 代理升级
	**/
	public function theAgent($uid)
	{
		$leaderId = M('users')->where('user_id', $uid)->value('first_leader');
		if(!$leaderId) return false;
		$top_level = new LevelLogic();
		//$result = $top_level->user_in($leaderId);
		return true;
	}
	//级差,平级
	public function sel($agentId,$price)
	{
		$meetUser = get_uper_user($agentId);
		if($meetUser['recUser'] && count($meetUser['recUser'])){
			$this->bonus($meetUser['recUser'],$price);
		}
	}


	public function bonus($meetUser,$price)
	{
		$logName  = '级差奖';
		//获取分红比例
		$rateArr  = $this->get_js_rate();
		$useRate = 0;
		$pj_money = 0;
		$userLevel = 0;
		$sourceType = 4;
		$is_top = false;
		foreach($meetUser as $k => $user){
			if($k<=0) continue;
			if(!$user['agent_user'] || $user['is_lock'] == 1) continue;
			$grade  = $user['agent_user'];
			if($grade < $userLevel) continue;
			$jsRate = intval($rateArr[$grade]) - $useRate;
			if($jsRate<0) continue;
			$money = ($price*$jsRate/100) * $this->goodNum;
			if($jsRate==0 && $grade==5) 
			{
				$jsRate  = $rateArr[127];
				$logName = '平级奖';
				$sourceType = 5;
				$money = ($pj_money*$jsRate/100) * $this->goodNum;
				$is_top = true;
			}
			$useRate = $rateArr[$grade];
			$userLevel = $grade;
			$pj_money = $money;
			$users = $this->first_leader($user['user_id']);
			$data = array(
				'user_money'=>$users['user_money']+$money
			);
			$res = M('users')->where(['user_id'=>$users['user_id']])->update($data);
			if($res)
			{
				$this->writeLog($users['user_id'],$money,$logName,101);
			}

			//平级脱离
			if($is_top){
				break;
			}
		}
	}

	//获取用户分红比例
	private function get_js_rate()
	{
		$user_level = M('user_level')->getField("level,rate");//->select();
        return $user_level;
	}
}