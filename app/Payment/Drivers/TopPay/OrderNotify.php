<?php

declare(strict_types=1);

namespace App\Payment\Drivers\TopPay;

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
        // {"code":"00","email":"xxx@xxx.com","method":"BNI","msg":"SUCCESS","name":"Test","orderNum":"toppay_38316110","payFee":"5630","payMoney":"10000","phone":"08123456789","platOrderNum":"PRE1815677431965556800","platSign":"S3HcafuwwmwhefuDY0s/BpeEIVY3CoOKHCiMbOmsehf3KQOX77NlHshUQLeyqsuosZFQ3nUJvBledAe2bkIKdqvTuunPSHHTSJ6v9Bwf+IGX4xZL3cPOJ6noTz3lAs4GctGZuPypl9X+Nnjjzitl3JRiiZoD9Mmkc1uzVvOS1O8=","status":"SUCCESS"}
        $callback = $request->all();

        $orderNo = $callback['orderNum'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo);

        // 三方验证签名
        $chkSign = $this->decryptSign($callback['platSign'], $order);
        $chkParams = $callback;
        unset($chkParams['platSign']);
        ksort($chkParams);
        $params_str = '';

        foreach ($chkParams as $key => $val) {
            $params_str = $params_str . $val;
        }

        if ($params_str !== $chkSign) {
            return Response::error('验证签名失败', ErrorCode::ERROR, $callback);
        }

        // 查詢訂單二次校驗訂單狀態
        $this->doubleCheck($callback, $orderNo, $order);

        $update = [
            'status' => $this->transformStatus($callback['status']),
            'real_amount' => $this->revertAmount($callback['payMoney']), // 三方返回的金額須轉換為 "分" 返回集成网关
            'trade_no' => $callback['platOrderNum'],
        ];
        $this->updateOrder($orderNo, $update);

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $callback['amount'] = $callback['payMoney'];
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

        // 验签成功, 调用第三方查询接口判断该订单在第三方系统里是否支付成功
        $response = $this->queryOrderInfo($order['query_url'], $order);

        if (1000 !== $response['code']) {
            throw new ApiException('查詢訂單失敗 ' . $orderNo);
        }

        if ($response['data']['status'] != $callback['status']) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        }
    }
}
