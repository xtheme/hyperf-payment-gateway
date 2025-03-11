<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Wt;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Config\config;

class OrderNotify extends OrderQuery
{
    /**
     * 三方回調通知, 更新訂單
     */
    public function request(RequestInterface $request): ResponseInterface
    {
        $callback = $request->all();

        // 回調請求中的商戶訂單號
        $orderNo = $callback['payment_cl_id'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo);

        // 验证签名
        if (false === $this->verifySignature($callback, $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 二次校驗
        $this->doubleCheck($callback, $orderNo, $order);

        // 更新订单
        $update = [
            'status' => $this->transformStatus($callback['status']),
            'real_amount' => $callback['real_amount'],
            'trade_no' => $callback['payment_id'],
        ];
        $this->updateOrder($orderNo, $update);

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $params = $this->transferOrderInfo($callback, $order);

        // 回调集成网关
        $response = $this->sendCallback($callbackUrl, $orderNo, $params);

        // 通知三方回调结果
        return $this->responsePlatform($response['code'] ?? '01');
    }

    /**
     * 若创建订单时带有三方查询网址 query_url 参数则可发起二次校验
     */
    private function doubleCheck(array $callback, string $orderNo, array $order): void
    {
        if (empty($order['query_url'])) {
            return;
        }

        // Custom header params
        $this->appendHeaders($order['header_params']);

        // 验签成功, 调用第三方查询接口判断该订单在第三方系统里是否支付成功
        $response = $this->queryOrderInfo($order['query_url'], $order);

        // 三方返回数据示例, data 為陣列
        // {
        //     "error_code": "0000",
        //     "data": [
        //         {
        //             "amount": 10000,
        //             "real_amount": 0,
        //             "fee": 0,
        //             "payment_id": "WUTONGPM00315302",
        //             "payment_cl_id": "wt_order98636755",
        //             "platform_id": "PF0252",
        //             "platform_channel_id": "PFC00000778",
        //             "status": 0,
        //             "memo": "",
        //             "create_time": 1679473246,
        //             "update_time": 1679473247,
        //             "sign": "1a71484abf72bacf2b4f7771f6914af3"
        //         }
        //     ],
        //     "total": 1
        // }

        if ('0000' != $response['error_code'] || 0 == $response['total']) {
            $this->logger->error('TP Error: ' . json_encode($response, JSON_UNESCAPED_SLASHES));

            throw new ApiException('查單失敗 ' . $orderNo);
        }

        // 三方訂單狀態與回調訂單狀態不一致
        if ($response['data'][0]['status'] != $callback['status']) {
            if ('dev' != config('app_env')) {
                throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
            }
            $this->logger->warning('訂單狀態確認失敗 ' . $orderNo);
        }

        // 二次校驗簽名
        if (false === $this->verifySignature($response['data'][0], $order['merchant_key'])) {
            throw new ApiException('二次验证签名失败 ' . $orderNo);
        }
    }
}
