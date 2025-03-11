<?php

declare(strict_types=1);

namespace App\Payment\Drivers\JyuYang;

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
        // {"payment_id":"LHPL00103171","payment_cl_id":"jyuyang_18425278","platform_id":"cn095","amount":"1000.0000","real_amount":"1000.0000","fee":"0.0000","status":2,"create_time":"2024-05-08T04:07:49.000Z","update_time":"2024-05-08T04:10:50.864Z","sign":"ffdb4e07e399368f7c2251512429865d"}
        $callback = $request->all();

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

        $update = [
            'status' => $this->transformStatus($callback['status']),
            'real_amount' => $callback['real_amount'],
            'trade_no' => $callback['payment_id'],
        ];
        $this->updateOrder($orderNo, $update);

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $callback['realAmount'] = $callback['real_amount'];
        $callback['merchantOrderId'] = $callback['payment_cl_id'];
        $callback['systemOrderId'] = $callback['payment_id'];
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

        if ('0000' != $response['code']) {
            $this->logger->error('TP Error: ' . json_encode($response, JSON_UNESCAPED_SLASHES));

            throw new ApiException('查單失敗 ' . $orderNo);
        }

        if ($response['data']['status'] != $callback['status']) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        }

        if (false === $this->verifySignature($response['data'], $order['merchant_key'])) {
            throw new ApiException('二次验证签名失败 ' . $orderNo);
        }
    }
}
