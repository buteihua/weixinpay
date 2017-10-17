微信支付php服务端实现,包含app支付，可以将代码拷贝到自己的工程添加相应的依赖，做些调整即可。

* [微信的配置文件] config／weixin.php
* [微信支付]    WechatPay.php  
*

统一下单
```php

    $params = array(
                'appid' => config('weixin.app_id'),
                'mch_id' => config('weixin.mch_id'),
                'nonce_str' => str_random(16),
                'body' => '藏拍' . $deposit->auction_name . '-保证金',
                'out_trade_no' => $deposit->out_trade_no,
                'total_fee' => $deposit->total_amount * 100, //微信支付单位为分
                'spbill_create_ip' => $_SERVER['SERVER_ADDR'],
                'notify_url' => config('weixin.deposit_notify_url'),
                'trade_type' => 'APP',
            );
            $params['sign'] = $this->wechat->getSign($params);
            $params = $this->wechat->arrayToXml($params);
            $rst = $this->wechat->curl_post_ssl('https://api.mch.weixin.qq.com/pay/unifiedorder', $params);
            $result = $this->wechat->xmlToArray($rst);

``` 

