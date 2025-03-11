<?php

declare(strict_types=1);

namespace App\Payment\Drivers\JyuYang;

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

        // {"code":"0000","data":{"systemOrderId":"LHPL00101716","amount":1000,"displayAmount":1000,"upstreamOrderId":"","upstreamLink":"https://checkout.78531659.com?c=993f338ce8586df769047b28afb9448f","cardName":"李娜拉","cardAccount":"6217866100003790202","cardBank":"中国银行"}
        if ('0000' != $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['data']['upstreamLink'] ?? '', // 支付链结
            'trade_no' => $response['data']['systemOrderId'] ?? '', // 支付交易订单号
            'payee_name' => $response['data']['cardName'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['data']['cardBank'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['data']['cardBranch'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['data']['cardAccount'] ?? '', // 收款人账号
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
            'merchantCode' => $data['merchant_id'],
            'merchantOrderId' => $orderNo,
            'amount' => $this->convertAmount($data['amount']),
            'notifyUrl' => $this->getNotifyUrl($data['payment_platform']),
            'payerName' => $data['user_name'],
            'request_time' => $this->getTimestamp(),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
