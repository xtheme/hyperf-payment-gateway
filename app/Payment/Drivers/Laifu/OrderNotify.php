<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Laifu;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OrderNotify extends OrderQuery
{
    /**
     * 三方回調通知, 更新訂單
     */
    public function request(RequestInterface $request): ResponseInterface
    {
        $callback = $request->all();

        // 回調請求中的商戶訂單號
        $orderNo = $callback['out_trade_no'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // {
        //     "amount": "100.00",
        //     "body": "order39217484",
        //     "channel": "9001",
        //     "order_status": "1",
        //     "out_trade_no": "laifu_order39217484",
        //     "remark": "no",
        //     "trade_no": "67616818942437766413628",
        //     "sign": "129d0e5a278b23fbe80bc2f7895711e3"
        // }

        // 查询订单
        $order = $this->getOrder($orderNo);

        // 验证签名
        if (false === $this->verifySignature($callback, $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 二次校驗
        $callback['status'] = $this->doubleCheck($callback, $orderNo, $order);

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
    private function doubleCheck(array $callback, string $orderNo, array $order): string
    {
        if (empty($order['query_url'])) {
            return '';
        }

        // 验签成功, 调用第三方查询接口判断该订单在第三方系统里是否支付成功
        $response = $this->queryOrderInfo($order['query_url'], $order);

        // Custom header params
        $this->appendHeaders($order['header_params']);

        // 支付状态, 1成功 其他失败
        if (1 != $callback['order_status']) {
            throw new ApiException('支付状态失败' . $orderNo);
        }

        // 查詢訂單時沒有簽名需要校驗
        // 回調時缺少訂單狀態, 需要返回
        return $response['data']['status'];
    }
}
