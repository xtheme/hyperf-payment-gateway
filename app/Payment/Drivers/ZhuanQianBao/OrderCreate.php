<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ZhuanQianBao;

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

        // 创建订单
        $this->createOrder($orderNo, $input);

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // 三方返回数据示例
        // {
        //     "id": "1204",
        //     "payment_url": "https://127.0.0.1?merchant_order_id=Test_1011-2&user_name=Wallegame&sign=0af4ba3b980a727f76c5daa98845428a"
        // }

        // 三方返回数据校验
        if (array_key_exists('error', $response)) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 验证签名
        // if ($response['sign'] === $this->getSignature($response['payParams'], $input['merchant_key'])) {
        //     return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        // }

        // 请依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo,                         // 订单号
            'link' => $response['payment_url'], // 支付网址
            'trade_no' => $response['id'],                               // 三方交易号
        ];

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        // 依据三方创建代收接口规范定义请求参数
        $params = [
            'user_name' => $data['merchant_id'],                  // 商户号
            'merchant_order_id' => $orderNo,                              // 商户订单号
            'collect_type' => $data['payment_channel'],
            'coin' => $this->convertAmount($data['amount']), // 支付金额 单位分
            'callback' => $this->getNotifyUrl($data['payment_platform']), // 支付结果异步回调URL
            'time_stamp' => $this->getDateTime(),
            'trans_name' => $data['user_name'],
            'trans_acc' => $data['bank_account'],
        ];

        if ('1' === $params['collect_type']) {
            $params['trans_bank'] = $data['account_bank'];
        }

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
