<?php
/**
 *  mete支付-支付宝接口
 *  Author : noname01t1(telegram)
 *  Date: 2023-5-12
 *  Reference:  1. 易支付-支付宝接口 Author : Alone88 web : https://alone88.cn
 *              2. mete支付v2board
 */

namespace Pay\meteali;

use \Pay\notify;

class meteali
{

    private $paymethod = "meteali";

    //处理请求
    public function pay($payconfig, $params)
    {

        try {


            $config = array(
                //商户id
                'appId' => $payconfig['app_id'],
                //支付类型
                'method' => 'ALIPAY_F2F',
                //系统订单号
                'outTradeNo' => $params['orderid'],
                // 商品名称
                //'name' => $params['productname'],
                //商品金额
                'totalAmount' => number_format($params['money'], 2, '.', ''),
                //网站名称
                //'sitename' => $params['webname'],
                //异步通知地址
                //'notify_url' => $params['weburl'] . '/product/notify/?paymethod=' . $this->paymethod,
                //异步跳转地址
                //'return_url' => $params['weburl'] . "/query/auto/{$params['orderid']}.html"
            );

            ksort($config);
            reset($config);
            $signStr = '';
            foreach ($config as $key => $value) {
                $signStr .= $key . '=' . urlencode($value) . '&';
            }
            $signStr = rtrim($signStr, '&');
            $md5Str = md5($signStr);
            $finalMd5Str = md5($md5Str . $payconfig['app_secret']);
            $config['sign'] = $finalMd5Str;

            
            // //排序数组
            // $config = $this->argSort($config);
            // // 转换成参数状态
            // $prestr = $this->createLinkstring($config);
            // //加上密钥
            // $data = md5($prestr . $payconfig['app_secret']);
            // $config['sign'] = $data;
            // $config['sign_type'] = strtoupper('MD5');

            //metele
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, 'https://metelep.xyz/api/v1/order/create');
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($config));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'User-Agent: MetePay']);

            $response = curl_exec($ch);
            $result = json_decode($response);
            
            if (!$result->success) {
                return array('code'=>1001,'msg'=>'支付接口请求失败','data'=>$md5Str . $payconfig['app_secret']);
            }

            curl_close($ch);
            
            $qr = $result->data->url;
            $money = $params['money'];
            //计算关闭时间
            $closetime = (int)290;
            $result = array('type'=>0,'subjump'=>0,'subjumpurl'=>'','paymethod'=>$this->paymethod,'qr'=>$params['qrserver'].$qr,'payname'=>$payconfig['payname'],'overtime'=>$closetime,'money'=>$money);
            return array('code'=>1,'msg'=>'success','data'=>$result);
            //获取url
            // $url = $payconfig['configure3'] . 'submit.php?' . $this->createLinkstring($config);
            // if($url){
            //     $result = array('type' => 1, 'subjump' => 0, 'paymethod' => $this->paymethod, 'url' => $url, 'payname' => $payconfig['payname'], 'overtime' => $payconfig['overtime'], 'money' => $params['money']);
            //     return array('code' => 1, 'msg' => 'success', 'data' => $result);
            // }else{
            //     return array('code'=>1001,'msg'=>'支付接口请求失败','data'=>'');
            // }
        } catch (\Exception $e) {
            return array('code' => 1000, 'msg' => $e->getMessage(), 'data' => '');
        }
    }

    //处理回调
    public function notify($payconfig)
    {
        try {
            //获取传入数据
            $inputString = file_get_contents('php://input', 'r');
            $inputStripped = str_replace(array("\r", "\n", "\t", "\v"), '', $inputString);
            $_POST = json_decode($inputStripped, true);
            $params = $_POST;
            //去除空值和签名参数
            $params = $this->paraFilter($params);
            //排序
            $params = $this->argSort($params);
            //签名
            $md5Sigm = md5($this->createLinkstring($params)) . $payconfig['app_secret'];
            $md5Sigm = md5($md5Sigm);
            
            // 验证签名数据
            if ($md5Sigm == $_POST['sign'] && $params['tradeStatus'] == 'TRADE_SUCCESS') {
                //成功
                //商户订单号
                $config = array('paymethod' => $this->paymethod, 'tradeNo' => $params['outTradeNo'], 'paymoney' => $params['totalAmount'], 'orderid' => $params['outTradeNo']);
                $notify = new \Pay\notify();
                $data = $notify->run($config);
                if ($data['code'] > 1) {
                    return 'error|Notify: ' . $data['msg'];
                } else {
                    return 'success';
                }
            } else {
                return 'error|Notify: auth fail';
            }

        } catch (\Exception $e) {
            file_put_contents(YEWU_FILE, CUR_DATETIME . '-' . $e->getMessage() . PHP_EOL, FILE_APPEND);
            exit;
        }
    }


    /**
     * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
     * @param $para 需要拼接的数组
     * return 拼接完成以后的字符串
     */
    function createLinkstring($para)
    {
        $arg = "";
        while (list ($key, $val) = each($para)) {
            $arg .= $key . "=" . $val . "&";
        }
        //去掉最后一个&字符
        $arg = substr($arg, 0, count($arg) - 2);

        //如果存在转义字符，那么去掉转义
        if (get_magic_quotes_gpc()) {
            $arg = stripslashes($arg);
        }
        return $arg;
    }

    /**
     * 除去数组中的空值和签名参数
     * @param $para 签名参数组
     * return 去掉空值与签名参数后的新签名参数组
     */
    function paraFilter($para)
    {
        $para_filter = array();
        while (list ($key, $val) = each($para)) {
            if ($key == "sign" || $key == "sign_type" || $val == "" || $key == 'paymethod') continue;
            else    $para_filter[$key] = $para[$key];
        }
        return $para_filter;
    }
    /**
     * 对数组排序
     * @param $para 排序前的数组
     * return 排序后的数组
     */
    function argSort($para)
    {
        ksort($para);
        reset($para);
        return $para;
    }
}
