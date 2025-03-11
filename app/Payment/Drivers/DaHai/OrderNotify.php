<?php

declare(strict_types=1);

namespace App\Payment\Drivers\DaHai;

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
        // {"TrackingNumber":"f71109c9-bcaf-4e08-b8b4-c9afcf1992f6","ShopUserLongId":"4f0f49bd-c1cc-449c-8231-cb6b6b926a7f","ShopOrderId":"dahai_43292268","PaymentChannelId":3,"CurrencyId":1,"Amount":3000,"RealAmount":3000,"AmountPaid":3000,"MonitorPayerName":null,"MonitorPayerAccountNumber":null,"MonitorTransferCode":null,"ShopCommissionAmount":0,"OrderStatusId":2,"FailedMessage":null,"OriginalOrderTrackingNumber":null,"ShopRemark":null,"IsTest":false,"PaymentReceivedAt":"2024-05-13 05:59:25","CreatedAt":"2024-05-13 05:56:56","EncryptValue":"4A29C6069B9E20EE062A9101E167F16C6EF56EEFC7D2A5D990BCA0E37F955B2A"}
        $callback = $request->all();

        $orderNo = $callback['ShopOrderId'] ?? '';

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
            'status' => $this->transformStatus($callback['OrderStatusId']),
            'real_amount' => $callback['RealAmount'],
            'trade_no' => $callback['TrackingNumber'],
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

        if (!$response['Success']) {
            $this->logger->error('TP Error: ' . json_encode($response, JSON_UNESCAPED_SLASHES));

            throw new ApiException('查單失敗 ' . $orderNo);
        }

        if ($response['Orders'][0]['OrderStatusId'] != $callback['OrderStatusId']) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        }
    }
}
