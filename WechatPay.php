<?php
namespace App\Tools;

use Log;

/**
 * wechat pay class
 */
class WechatPay {

    /**
     *  作用：array转xml
     */
    function arrayToXml($arr) {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";

            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }

        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     *  作用：将xml转为array
     */
    public function xmlToArray($xml) {
        //将XML转为array
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $array_data;
    }

    function curl_post_ssl($url, $vars, $second = 30, $aHeader = array()) {
        $ch = curl_init();
        //超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '10.206.30.98');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSLCERT, config('weixin.apiclient_cert'));
        curl_setopt($ch, CURLOPT_SSLKEY, config('weixin.apiclient_key'));
        curl_setopt($ch, CURLOPT_CAINFO, config('weixin.rootca'));

        if (count($aHeader) >= 1) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        }

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);
        $data = curl_exec($ch);
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }
    }

    /**
     *  作用：格式化参数，签名过程需要使用
     */
    function formatBizQueryParaMap($paraMap, $urlencode = false) {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if ($urlencode) {
                $v = urlencode($v);
            }
            //$buff .= strtolower($k) . "=" . $v . "&";
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar;
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff) - 1);
        }
        return $reqPar;

    }

    /**
     *  作用：生成签名
     */
    public function getSign($Obj) {
        foreach ($Obj as $k => $v) {
            $Parameters[$k] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //echo '【string1】'.$String.'</br>';
        //签名步骤二：在string后加入KEY
        $String = $String . "&key=" . config('weixin.api_key');
        // dd($String);
        //echo "【string2】".$String."</br>";
        //签名步骤三：MD5加密
        $String = md5($String);
        //echo "【string3】 ".$String."</br>";
        //签名步骤四：所有字符转为大写
        $result_ = strtoupper($String);
        //echo "【result】 ".$result_."</br>";
        return $result_;
    }

    //查询订单
    public function queryOrder($data) {
        if ($data['out_trade_no']) {
            $params['out_trade_no'] = $data['out_trade_no'];
        } else {
            $params['transaction_id'] = $data['transaction_id'];
        }
        $params['appid'] = config('weixin.app_id');
        $params['mch_id'] = config('weixin.mch_id');
        $params['nonce_str'] = str_random(16);
        $params['sign'] = $this->getSign($params);
        $params = $this->arrayToXml($params);
        $rst = $this->curl_post_ssl('https://api.mch.weixin.qq.com/pay/orderquery', $params);
        $result = $this->xmlToArray($rst);
        Log::info('微信查询结果' . json_encode($result));
        if (array_key_exists("return_code", $result)
            && array_key_exists("result_code", $result)
            && $result["return_code"] == "SUCCESS"
            && $result["result_code"] == "SUCCESS"
            && $result["trade_state"] == "SUCCESS") {
            return true;
        }
        return false;
    }

    //申请退款
    public function refund($data) {
        $params = array(
            'appid' => config('weixin.app_id'),
            'mch_id' => config('weixin.mch_id'),
            'nonce_str' => str_random(16),
            'transaction_id' => $data['trade_no'],
            'out_refund_no' => $data['refund_trade_no'],
            'total_fee' => intval($data['total_amount'] * 100),
            'refund_fee' => intval($data['total_amount'] * 100),
            'refund_desc' => '退还保证金',
        );
        $params['sign'] = $this->getSign($params);
        $params = $this->arrayToXml($params);
        $rst = $this->curl_post_ssl('https://api.mch.weixin.qq.com/secapi/pay/refund', $params);
        $result = $this->xmlToArray($rst);
        dd($result);
        Log::info('微信退款结果' . json_encode($result));
        if (array_key_exists("return_code", $result)
            && array_key_exists("result_code", $result)
            && $result["return_code"] == "SUCCESS"
            && $result["result_code"] == "SUCCESS"
            && $result["trade_state"] == "SUCCESS") {
            if (!$this->refundQuery($data['trade_no'])) {
                return false;
            }
            return true;
        }
        return false;
    }

    //查询退款
    public function refundQuery($transaction_id) {
        $params = array(
            'appid' => config('weixin.app_id'),
            'mch_id' => config('weixin.mch_id'),
            'nonce_str' => str_random(16),
            'transaction_id' => $data['out_trade_no'],
        );
        $params['sign'] = $this->getSign($params);
        $params = $this->arrayToXml($params);
        $rst = $this->curl_post_ssl('https://api.mch.weixin.qq.com/pay/refundquery', $params);
        $result = $this->xmlToArray($rst);
        Log::info('微信查询退款结果' . json_encode($result));
        if (array_key_exists("return_code", $result)
            && array_key_exists("result_code", $result)
            && $result["return_code"] == "SUCCESS"
            && $result["result_code"] == "SUCCESS"
            && $result["trade_state"] == "SUCCESS") {
            return true;
        }
        return false;
    }
}