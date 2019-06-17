<?php
use think\Route;
//
//return [
//    '__pattern__' => [
//        'name' => '\w+',
//    ],
//    '[hello]'     => [
//        ':id'   => ['index/hello', ['method' => 'get'], ['id' => '\d+']],
//        ':name' => ['index/hello', ['method' => 'post']],
//    ],
//    //'goodsInfo/[:id]' => ['Mobile/Goods/goodsInfo',['method' => 'get', 'ext' => 'html'],'cache'=>3600]
//    //Mobile/Goods/goodsInfo/id/104.html
//];
//use think\Route;
// 注册路由到index模块的News控制器的read操作
//Route::get('goodsInfo/:id','Mobile/Goods/goodsInfo',['cache'=>['Mobile/Goods/goodsInfo',300]]);// 访问方式 http://www.tpshop2.0.com/goodsInfo/77.html

// http://www.tpshop2.0.com/Mobile/Goods/goodsInfo/id/77.html

Route::any('report','mobile/test/index');