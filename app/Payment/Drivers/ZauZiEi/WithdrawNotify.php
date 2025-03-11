<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ZauZiEi;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class WithdrawNotify extends WithdrawQuery
{
    /**
     * 三方回調通知, 更新訂單
     */
    public function request(RequestInterface $request): ResponseInterface
    {
        // {"userid":"2024043256","orderno":"zauziei_withdraw_17777032","outorder":"zauziei_withdraw_17777032","amount":"1000.00","fee":"3.00","status":"1","account":"833696030002","name":"測試","bank":"中信银行","subbranch":"北京分行","province":"","city":"","bankno":"","sign":"05ea7abdd84a186d8a8c84f3c90e519f"} {"request-id":"018f3877-d12d-72e6-97c8-2ced44aeaee5"}
        $callback = $request->all();

        $orderNo = $callback['orderno'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        $signParams = [
            'userid' => $callback['userid'],
            'orderno' => $callback['orderno'],
            'outorder' => $callback['outorder'],
            'status' => $callback['status'],
            'amount' => $callback['amount'],
            'fee' => $callback['fee'],
            'account' => $callback['account'],
            'name' => $callback['name'],
            'bank' => $callback['bank'],
            $this->signField => $callback['sign'],
        ];

        if (false === $this->verifySignature($signParams, $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 二次校驗
        $queryRes = $this->doubleCheck($callback, $orderNo, $order);
        $callback['content'] = $queryRes['content'];

        // 更新订单
        $update = [
            'status' => $this->transformWithdrawStatus($callback['status']),
            'trade_no' => $callback['outorder'],
            'fee' => $this->revertAmount($callback['fee']),
        ];
        $this->updateOrder($orderNo, $update, 'withdraw');

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $order['trade_no'] = $update['trade_no'];
        $order['fee'] = $callback['fee'];
        $params = $this->transferOrderInfo($callback, $order);

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

        if ('1' === $callback['status']) {
            if ('1' !== $response['content']['orderstatus']) {
                throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
            }
        } else {
            if ('3' !== $response['content']['orderstatus']) {
                throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
            }
        }

        return $response;
    }
}
