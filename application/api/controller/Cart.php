<?php
/**
 * 购物车API
 */
namespace app\api\controller;
use app\common\model\Users;
use app\common\logic\UsersLogic;
use app\common\logic\CartLogic;
use think\Db;

class Cart extends ApiBase
{

    /**
     * 将商品加入购物车.
     *
     * @param token 登录凭证
     */
    public function addcart()
    {
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }





        $data = '购物车数据';
        $this->ajaxReturn(['status' => 0 , 'msg'=>'加入购物车成功','data'=>$data]);
    }

    
    /*
     * 请求获取购物车列表
     */
    public function cartlist()
    {

        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($user_id);
        $data = $cartLogic->getCartList();//用户购物车
        $seller = Db::name('seller')->select();
        /*foreach ($data as $k=>$v) {
            if($v['goods']['seller_id']==$seller[0]['seller_id']){
                $v['seller_name']=$seller[0]['seller_name'];
            }else{
                $v['seller_name']="";
            }
        }*/
        foreach($data as $k=>$v){
        unset($v['user_id']);
        unset($v["session_id"]);
        unset($v["goods_id"]);
        unset($v["goods_name"]);
        unset($v["market_price"]);
        unset($v["member_goods_price"]);
        unset($v["item_id"]);
        unset($v["spec_key"]);
        unset($v["bar_code"]);
        unset($v["add_time"]);
        unset($v["prom_type"]);
        unset($v["prom_id"]);
        unset($v["sku"]);
        unset($v["combination_group_id"]);
        }
        
        $res[0] = array(
            'seller_id'=> 0,
            'seller_name'=>'ZF智丰自营',
            'data'=>$data,
        );
        $this->ajaxReturn(['status' => 0 , 'msg'=>'购物车列表成功','data'=>$res]);
    }


     /**
     * 删除购物车的商品
     */
    public function delcart()
    {
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $id = I('id/a');
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($user_id);
        $data = $cartLogic->delete($id);
        if($data){
            $this->ajaxReturn(['status' => 0 , 'msg'=>'删除成功','data'=>$data]);
        }else{
            $this->ajaxReturn(['status' => -1 , 'msg'=>'删除失败','data'=>$data]);
        }
        
    }


    /**
     * 更新数量
     */
    public function update_num()
    {

    }

    /**
     * +---------------------------------
     * 更新购物车，并返回计算结果
     * +---------------------------------
    */
    public function AsyncUpdateCart()
    {
        $user_id = $this->get_user_id();
        if(!$user_id){
            $this->ajaxReturn(['status' => -1 , 'msg'=>'用户不存在','data'=>'']);
        }
        $cart = input('cart/a', []);
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($user_id);
        $cartLogic->AsyncUpdateCart($cart);
        $select_cart_list = $cartLogic->getCartList(1);//获取选中购物车
        $cart_price_info = $cartLogic->getCartPriceInfo($select_cart_list);//计算选中购物车
        $user_cart_list = $cartLogic->getCartList();//获取用户购物车
        // $return['cart_list'] = $cartLogic->cartListToArray($user_cart_list);//拼接需要的数据
        $return['cart_price_info'] = $cart_price_info;
        $this->ajaxReturn(['status' => 0 , 'msg'=>'计算成功','data'=>$return]);
    }

    
    /* +---------------------------------
     * 购物车加减
     * +---------------------------------
    */
    public function changeNum(){
        $cart = input('cart/a',[]);
        if (empty($cart)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '请选择要更改的商品', 'result' => '']);
        }
        $cartLogic = new CartLogic();
        $result = $cartLogic->changeNum($cart['id'], $cart['goods_num']);
        $this->ajaxReturn(['status' => 0 , 'msg'=>'修改成功','data'=>$result]);

    }


    
    
}
