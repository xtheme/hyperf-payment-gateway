<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Sulifu;

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

        // 三方返回数据校验
        if (1 !== $response['Success']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input, 'withdraw');

        // 依据三方创建代付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo, // 订单号
            'trade_no' => $response['oid'] ?? '', // 三方交易号
        ];

        // 更新訂單
        $this->updateOrder($orderNo, $data, 'withdraw');

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        // 依据三方创建代付接口的参数调整以下字段
        $params = [
            'merNo' => $data['merchant_id'],
            'tradeNo' => $orderNo,
            'cType' => $data['payment_channel'],
            'bankCode' => 'dp_' . $data['bank_code'], // 由 GP 帶入之 bankCode 取得，於前方加入'dp_' 後帶入渠道（Ex:台新銀行為 dp_812）
            // 'bankBranch' => $data['bank_branch_name'],
            // 'branchCode' => $data['bank_branch_code'],
            'bankCardNo' => $data['bank_account'],
            'orderAmount' => $this->convertAmount($data['amount']),
            'accountName' => $data['user_name'],
            'openProvince' => '1',
            'openCity' => '1',
            'notifyUrl' => $this->getWithdrawNotifyUrl($data['payment_platform']),
        ];

        // 參與簽名字段, 有順序性
        $signParams = [
            'merNo' => $params['merNo'],
            'tradeNo' => $params['tradeNo'],
            'bankCode' => $params['bankCode'],
            'orderAmount' => $params['orderAmount'],
        ];
        // 加上签名
        $params[$this->signField] = $this->getSignature($signParams, $data['merchant_key']);

        return $params;
    }
}
