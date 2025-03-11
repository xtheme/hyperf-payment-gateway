<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ZauZiEi;

use App\Common\Response;
use App\Constants\ErrorCode;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class WithdrawCreate extends Driver
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
        if ($this->isOrderExists($orderNo, 'withdraw')) {
            return Response::error('商户订单号不允许重复', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 準備三方請求參數
        $params = $this->prepareOrder($orderNo, $input);

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // {"userid":"2024043256","status":1,"orderno":"zauziei_withdraw_51811075","amount":"1000.00","msg":"Successful"}
        if (1 !== $response['status']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input, 'withdraw');

        $data = [
            'order_no' => $orderNo, // 订单号
            'trade_no' => $response['payout_id'] ?? '', // 三方交易号
        ];

        // 更新訂單 trade_no
        $update = [
            'trade_no' => $data['trade_no'],
        ];
        $this->updateOrder($orderNo, $update, 'withdraw');

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        $content = [
            'date' => $this->getDateTime('YmdHis'),
            'amount' => $this->convertAmount($data['amount']),
            'bank' => $data['bank_name'], // 开户银行
            'orderno' => $orderNo,
            'name' => $data['user_name'], // 收款人姓名
            'subbranch' => $data['branch_name'], // 开户支行
            'account' => $data['bank_account'], // 收款账户
        ];

        $params = [
            'userid' => $data['merchant_id'],
            'action' => 'withdraw',
            'notifyurl' => $this->getWithdrawNotifyUrl($data['payment_platform']),
            'content' => json_encode([$content]),
        ];
        $signParams = [
            'userid' => $params['userid'],
            'action' => $params['action'],
            'content' => $params['content'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($signParams, $data['merchant_key']);

        return $params;
    }
}
