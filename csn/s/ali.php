<?php

// 支付宝相关配置
return [
    // 应用ID,您的APPID
    'app_id' => '',
    // 商户私钥
    'merchant_private_key' => '',
    // 异步通知地址
    'notify_url' => '',
    // 同步跳转
    'return_url' => '',
    // 编码格式
    'charset' => 'UTF-8',
    // 签名方式
    'sign_type' => 'RSA2',
    // 支付宝网关
    'gatewayUrl' => 'https://openapi.alipay.com/gateway.do',
    // 支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥
    'alipay_public_key' => '',


    // 必填，接口名称，固定值
    'service'        => 'mobile.securitypay.pay',
    // 必填，合作商户号// 支付宝合作者身份ID，以2088开头的16位纯数字
    'partner'        => "2088221292358183",
    // 必填，参数编码字符集
    '_input_charset' => 'UTF-8',
    // 必填，商户网站唯一订单号
    'out_trade_no'   => date('YmdHis', time()),
    // 必填，商品名称
    'subject'        => '学员约车',
    // 必填，支付类型
    'payment_type'   => '1',
    // 必填，卖家支付宝账号
    'seller_id'      => "finance@jyouw.cn",
    // 必填，总金额，取值范围为[0.01,100000000.00]
    'total_fee'      => 0.01,
    // 必填，商品详情
    'body'           => '学员科二科三约车练习支付',
    // 可选，未付款交易的超时时间
    'it_b_pay'       => '1d',
    // 可选，服务器异步通知页面路径
    'notify_url'     => urlencode('http://www.jyouw.cn/jiayouxueche/io/alipay/notify.php'),
    // 可选，商品展示网站
    'show_url'       => urlencode('http://www.jyouw.cn/jiayouxueche/h5.1.3/kemu1.html')
];