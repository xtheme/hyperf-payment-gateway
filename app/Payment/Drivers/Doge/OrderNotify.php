<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Doge;

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
        // {"data":{"order_number":"doge_30979743","system_order_number":"GX20240430134520121858","username":"yg8888","amount":"2000.00","status":5,"sign":"0505f6b6973ba2b405aacea23d09da25"},"http_status_code":200,"error_code":0,"message":"异步回调"}
        $callback = $request->all();

        $orderNo = $callback['data']['order_number'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo);

        // 验证签名
        if (false === $this->verifySignature($callback['data'], $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 二次校驗
        $queryRes = $this->doubleCheck($callback, $orderNo, $order);

        $update = [
            'status' => $this->transformStatus($callback['data']['status']),
            'real_amount' => $this->revertAmount($callback['data']['amount']),
            'trade_no' => $callback['data']['system_order_number'],
        ];
        $this->updateOrder($orderNo, $update);

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $params = $this->transferOrderInfo($queryRes, $order);

        // 回调集成网关
        $response = $this->sendCallback($callbackUrl, $orderNo, $params);

        // 通知三方回调结果
        return $this->responsePlatform($response['code'] ?? '01');
    }

    /**
     * 若创建订单时带有三方查询网址 query_url 参数则可发起二次校验
     */
    private function doubleCheck(array $callback, string $orderNo, array $order): array
    {
        if (empty($order['query_url'])) {
            return [];
        }

        // 验签成功, 调用第三方查询接口判断该订单在第三方系统里是否支付成功
        $response = $this->queryOrderInfo($order['query_url'], $order);

        // Custom header params
        $this->appendHeaders($order['header_params']);

        if ($response['data']['status'] != $callback['data']['status']) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        }

        if (false === $this->verifySignature($response['data'], $order['merchant_key'])) {
            throw new ApiException('二次验证签名失败 ' . $orderNo);
        }

        return $response['data'];
    }
}
