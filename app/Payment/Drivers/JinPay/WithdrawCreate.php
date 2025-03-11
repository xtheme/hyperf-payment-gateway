<?php

declare(strict_types=1);

namespace App\Payment\Drivers\JinPay;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
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

        // {"code":0,"msg":"success","data":{"orderId":"202408021511410223","orderNo":"jinpay_withdraw_94063113","amount":"10000","userId":"IDS88","acctno":"000000000000","acctname":"Tester","sign":"e45ca1645d54acb0cf1710b623c76ad6"}}
        if (0 !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        if (false === $this->verifySignature($response['data'], $input['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input, 'withdraw');

        $data = [
            'order_no' => $orderNo, // 订单号
            'trade_no' => $response['data']['orderId'] ?? '', // 三方交易号
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
        // 有填銀行代碼的話再檢查
        if (!isset(self::BANK_CODE_MAP[$data['bank_code']])) {
            throw new ApiException('渠道不支持此家银行代付 ' . $data['bank_code']);
        }

        $params = [
            'appid' => $data['merchant_id'],
            'acctno' => $data['bank_account'],
            'apporderid' => $orderNo,
            'notifyurl' => $this->getWithdrawNotifyUrl($data['payment_platform']),
            'amount' => strval(intval($this->convertAmount($data['amount']))),
            'ordertime' => $this->getDateTime('YmdHis'),
            'acctname' => $data['user_name'],
            'bankcode' => self::BANK_CODE_MAP[$data['bank_code']],
            'currency' => 'IDR',
        ];

        $signParams = $params;
        unset($signParams['currency']);

        // 加上签名
        $params[$this->signField] = $this->getWithdrawSignature($signParams, $data['merchant_key']);

        return $params;
    }
}
