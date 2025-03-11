<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ZauZiEi;

use App\Common\Response;
use App\Constants\ErrorCode;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OrderCreate extends Driver
{
    /**
     * 创建代收订单, 返回支付网址
     */
    public function request(RequestInterface $request): ResponseInterface
    {
        $input = $request->all();

        // 商户订单号
        $orderNo = $input['order_id'];

        // 檢查訂單號
        if ($this->isOrderExists($orderNo)) {
            return Response::error('商户订单号不允许重复', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 准备三方请求参数
        $params = $this->prepareOrder($orderNo, $input);

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // {"status":1,"data":{"name":"对接测试","bank":"上海银行","subbank":null,"bankaccount":"32645654","amount":1000}}
        // {"status":1,"payurl":"https://adminpp.cc/Apipay/checkpage/ordernum/zauziei_80219314"} {"request-id":"018f2dd3-1cff-7187-9640-f1d2ab75a849"}
        if (1 !== $response['status']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['payurl'] ?? '', // 支付链结
            'trade_no' => $response['trade_no'] ?? '', // 支付交易订单号
            'payee_name' => $response['data']['name'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['data']['bank'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['data']['subbank'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['data']['bankaccount'] ?? '', // 收款人账号
            'cashier_link' => $this->getCashierUrl($orderNo), // 收銀台網址
        ];
        $this->updateOrder($orderNo, $data);

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        $params = [
            'userid' => $data['merchant_id'], // 用户ID
            'orderno' => $orderNo, // 用户订单号
            'desc' => $orderNo, // 订单说明
            'amount' => $this->convertAmount($data['amount']), // 金額（元）精確到小數點兩位
            'notifyurl' => $this->getNotifyUrl($data['payment_platform']), // 异步通知地址
            'backurl' => $this->getReturnUrl(), // 异步通知地址 支付成功后跳转到的地址
            'paytype' => 'bank_auto',
            // 'bankstyle' => '1' ,
            'acname' => $data['user_name'],
            'attach' => 'CNY',
            'userip' => getClientIp(), // 传回玩家提单IP.
            'currency' => 'CNY',
        ];

        if ('1' == $data['payment_channel']) {
            $params['bankstyle'] = $data['payment_channel'];
        }

        // sign params.
        $signParams = [
            'userid' => $params['userid'],
            'orderno' => $params['orderno'],
            'amount' => $params['amount'],
            'notifyurl' => $params['notifyurl'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($signParams, $data['merchant_key']);

        return $params;
    }
}
