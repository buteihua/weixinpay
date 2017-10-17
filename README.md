微信支付php服务端实现,包含app支付，可以将代码拷贝到自己的工程添加相应的依赖，做些调整即可。

* [微信的配置文件] config／weixin.php
* [微信支付]    WechatPay.php  
*

统一下单
```php

    $params = array(
        'appid' => config('weixin.app_id'),       //微信开放平台审核通过的应用APPID
        'mch_id' => config('weixin.mch_id'),      //微信支付分配的商户号
        'nonce_str' => str_random(16),            //随机字符串，不长于32位
        'body' => '藏拍',                         //商品描述
        'out_trade_no' => '20171017091021',       //商户订单号     
        'total_fee' => 1.00 * 100,                //总金额(微信支付单位为分)
        'spbill_create_ip' => $_SERVER['SERVER_ADDR'],//终端IP
        'notify_url' => config('weixin.notify_url'),  //通知地址
        'trade_type' => 'APP',                          //交易类型
    );
    $params['sign'] = $this->wechat->getSign($params);  //签名
    $params = $this->wechat->arrayToXml($params);
    $rst = $this->wechat->curl_post_ssl('https://api.mch.weixin.qq.com/pay/unifiedorder', $params);
    $result = $this->wechat->xmlToArray($rst);

``` 

调起支付
``` php

    $res['appid'] = config('weixin.app_id');                //应用ID
    $res['partnerid'] = config('weixin.mch_id');            //商户号
    $res['prepayid'] = $result['prepay_id'];                //预支付交易会话ID
    $res['package'] = "Sign=WXPay";               //扩展字段暂填写固定值Sign=WXPay
    $res['noncestr'] = str_random(16);                      //随机字符串
    $res['timestamp'] = time();                             //时间戳
    $res['sign'] = $this->wechat->getSign($res);            //签名
    $res['out_trade_no'] = $deposit->out_trade_no;          //方便同步验证查询方面自己加的商户订单号
    return $this->response(array('wechatpay' => $res));     //返给客户端用来调起微信支付


```

