<?php

declare(strict_types=1);

namespace App\Payment\Drivers\LiMaPay;

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

        /*
        {"status":"1","data":{"url":"https://otc.babeebas.uk?billId=662c9282a5c7464057519b57&theme=undefined","bankname":"","bankusername":"","bankcode":"","bankaddress":"","bank_yuliu1":"","bank_yuliu2":"","bank_yuliu3":"","money":"0"},"data2":"","message":"","page":""}
        */
        if ('1' !== $response['status']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['data']['url'] ?? '', // 支付链结
            'trade_no' => $response['data']['trade_no'] ?? '', // 支付交易订单号
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
            'merchant_id' => $data['merchant_id'], // 商戶號
            'merchant_orderid' => $orderNo, // 商戶訂單號
            'money' => $this->convertAmount($data['amount']), // 金額（元）精確到小數點兩位
            'notifyurl' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
            'paytype' => 'CNY-BANK2BANK', // 支付編碼
            'realname' => $data['user_name'], // 存款人的真实姓名
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
