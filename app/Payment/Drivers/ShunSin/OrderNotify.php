<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ShunSin;

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
        // {"merchantId":"YG8888","merchantOrderId":"shunsin_53301354","orderAmount":"1000.00","systemOrderId":"05d9bda6cb0d4750b6d258a901406ae5","channelType":"BANK_PAY","remark":"Tester","ip":"35.220.141.254","sign":"1f777ae39ebb984e51663c6c74e03cd8"}
        $callback = $request->all();

        $orderNo = $callback['merchantOrderId'] ?? '';

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
            'status' => '2',
            'real_amount' => $callback['orderAmount'],
            'trade_no' => $callback['systemOrderId'],
        ];
        $this->updateOrder($orderNo, $update);

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $callback['MerchantOrderId'] = $callback['merchantOrderId'];
        $callback['SystemOrderId'] = $callback['systemOrderId'];
        $callback['OrderAmount'] = $callback['orderAmount'];
        $callback['ErrorCode'] = '00';
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

        if ('2' != $this->transformStatus($response['ErrorCode'])) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        }
    }
}
