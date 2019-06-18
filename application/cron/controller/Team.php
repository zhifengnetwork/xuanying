<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/13 0013
 * Time: 10:20
 */

namespace app\cron\controller;

use think\Controller;
use think\Db;
use app\common\util\Exception;
use app\common\logic\UsersLogic;

class Team extends Controller{
    /**
     * 执行方法
     */
    public function run()
    {
        //对过期的拼团订单进行取消,在服务器上由定时器任务执行
        $Tf = M('team_found');
        $time = time() - 3600 * 48;  //只取两天以内的
        $list = $Tf->field('f.found_id')->alias('f')->join('tp_team_activity t','f.team_id=t.team_id','left')->where('f.found_time between ' . $time . " and (unix_timestamp(now())-t.time_limit*3600) and f.need>0")->select();
        $Tf->alias('f')->join('tp_team_activity t','f.team_id=t.team_id','left')->where('f.found_time between ' . $time . " and (unix_timestamp(now())-t.time_limit*3600) and f.need>0")->update(['f.status'=>3]);

        $Tfw = M('team_follow');
        $Order = M('Order');
        $Users = M('users');
        $AccountLog = M('account_log');
        foreach($list as $v){ 
            $Tfw->where(['found_id'=>$v['found_id']])->update(['status'=>3]); 
            $oflist = $Order->field('order_id,order_sn,user_id,integral_money,total_amount')->where(['order_prom_id'=>$v['found_id'],'pay_status'=>1,'order_status'=>0])->select();

            foreach($oflist as $v1){
                if($v1['total_amount']){
                    $Users->where(['user_id'=>$v1['user_id']])->setInc('credit2',$v1['total_amount']);     
                    $AccountLog->add(['user_id'=>$v1['user_id'],'user_money'=>$v1['total_amount'],'pay_points'=>$v1['integral_money'],'change_time'=>time(),'desc'=>'拼团失败返回','order_sn'=>$v1['order_sn'],'order_id'=>$v1['order_id'],'states'=>103]);
                }
                if($v1['integral_money'])
                    $Users->where(['user_id'=>$v1['user_id']])->setInc('pay_points',$v1['integral_money']);      
            }
            $Order->where(['order_prom_id'=>$v['found_id']])->update(['order_status'=>3]);
        }
        
        //竞拍未成功的人返回保证金
        $Auction = M('Auction');
        $AuctionDeposit = M('Auction_deposit');
        $AuctionPprice = M('Auction_price');
        $alist = $Auction->field('id,deposit,payment_time,end_time')->where(['end_time'=>['gt',(time()-360)],'is_end'=>1])->select();
        foreach($alist as $v2){
            $aplist = $AuctionPprice->field('user_id')->where(['is_out'=>['neq',2],'auction_id'=>$v2['id']])->grouy('user_id')->select();  
            //成交用户
            $uid = $AuctionPprice->field('user_id')->where(['is_out'=>2,'auction_id'=>$v2['id']])->column('user_id');     
            foreach($aplist as $v3){
                if($v3['user_id'] == $uid)continue;
                $order_sn = $AuctionDeposit->where(['user_id'=>$v3['user_id'],'auction_id'=>$v2['id'],'is_back'=>0])->value('order_sn');
                if(!$order_sn)continue;
                $AuctionDeposit->where(['user_id'=>$v3['user_id'],'auction_id'=>$v2['id']])->update(['is_back'=>1]);    
                $Users->where(['user_id'=>$v3['user_id']])->setInc('pay_points',$v2['deposit']);  
                $AccountLog->add(['user_id'=>$v3['user_id'],'user_money'=>$v2['deposit'],'change_time'=>time(),'desc'=>'竞拍失败保证金返回','states'=>104]);  
            }
        }
               
    }

    //大礼包季度执行，每季度1号00:02:00执行
    public function GiftCheck(){
        //获取上季度的开始结束时间戳
        $season = ceil((date('n'))/3);//上季度
        $start = mktime(0, 0, 0,$season*3-3+1,1,date('Y')); //季度开始时间戳
        $end = mktime(23,59,59,$season*3,date('t',mktime(0, 0 , 0,$season*3,1,date("Y"))),date('Y')); //季度结束时间戳       
    
        //获取所有城市合伙人
        $Users = M('Users');
        $AccountLog = M('Account_log');
        $list = $Users->where(['is_cityvip'=>1])->column('user_id'); $list= [1];
        $UsersLogic = new UsersLogic();
        foreach($list as $v){   
            //获取团队业绩
            $bot_arr = $UsersLogic->getUserLevBotAll($v,$bot_arr);  //获取所有下级
            $bot_arr[] = $v;
            $total_amount = Db::name('order')->master()->where(['user_id' => ['in',$bot_arr], 'pay_status' => 1, 'order_status' => ['NOTIN', [3, 5]]])->sum('order_amount+user_money'); 
            if($total_amount >= 2059200){ //12%
                $price = floor(($total_amount * 12))/100;
            }elseif($total_amount >= 1663200){ //11%
                $price = floor(($total_amount * 11))/100;
            }elseif($total_amount >= 1029600){ //10%
                $price = floor(($total_amount * 10))/100;
            }elseif($total_amount >= 633600){ //9%
                $price = floor(($total_amount * 9))/100;
            }elseif($total_amount >= 435600){ //8%
                $price = floor(($total_amount * 8))/100;
            }elseif($total_amount >= 316800){ //7%
                $price = floor(($total_amount * 7))/100;
            }elseif($total_amount >= 198000){ //6%
                $price = floor(($total_amount * 6))/100;
            }elseif($total_amount >= 99000){ //5%
                $price = floor(($total_amount * 5))/100;
            }elseif($total_amount >= 59400){ //4%
                $price = floor(($total_amount * 4))/100;
            }
            if(isset($price) && $price){
                $Users->where(['user_id'=>$v])->setInc('user_money',$price);
                $AccountLog->add(['user_id'=>$v,'user_money'=>$price,'change_time'=>time(),'desc'=>'您的团队本季度已达到分红条件','states'=>109]);
            }
        }

        //清空上季度的quarter_bonus
        $Users->where(['quarter_bonus'=>1])->update(['quarter_bonus'=>0]);
    }

}