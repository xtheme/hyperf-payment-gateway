<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ManHe;

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

        // {"error_code":"0000","data":{"link":"https://jiuxin.info/gateway/portal/v2/payments/?payment_id=STARDREAMPM00033876&sign=cd1cba45668a5cab261218be88d7d3a1","payment_info":{"amount":100000,"display_amount":100000,"payment_id":"STARDREAMPM00033876","payment_cl_id":"manhe_90270751","receiver":{"card_name":"王振凯","card_number":"6210134950934943","bank_name":"湖北农信","bank_branch":"清明河支行","bank_logo":"https://apimg.alipay.com/combo.png?d=cashier&t=HURCB"},"sender":{"card_name":"Tester","card_number":"*************","bank_id":"BK0000","bank_code":"DEFAULT","bank_name":"","bank_logo":"https://apimg.alipay.com/combo.png?d=cashier&t=DEFAULT"},"token":"无须附言"}}}
        if ('0000' !== $response['error_code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['data']['link'] ?? '', // 支付链结
            'trade_no' => $response['data']['payment_info']['payment_id'] ?? '', // 支付交易订单号
            'payee_name' => $response['data']['payment_info']['receiver']['card_name'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['data']['payment_info']['receiver']['bank_name'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['data']['payment_info']['receiver']['bank_branch'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['data']['payment_info']['receiver']['card_number'] ?? '', // 收款人账号
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
            'platform_id' => $data['merchant_id'], // 商戶號
            'service_id' => $data['payment_channel'],
            'payment_cl_id' => $orderNo,
            'name' => $data['user_name'],
            'amount' => $this->convertAmount($data['amount']),
            'notify_url' => $this->getNotifyUrl($data['payment_platform']),
            'request_time' => strval(round(microtime(true))),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
