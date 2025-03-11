<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Juying;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Config\config;

class WithdrawNotify extends WithdrawQuery
{
    /**
     * 三方回調通知, 更新訂單
     */
    public function request(RequestInterface $request): ResponseInterface
    {
        $callback = $request->all();

        // 回調請求中的商戶訂單號
        $orderNo = $callback['orderNo'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        // 參與簽名參數
        $sign_params = [
            'mch_id' => $callback['mch_id'],
            'nonce_str' => $callback['nonce_str'],
            'orderNo' => $callback['orderNo'],
            'score' => $callback['score'],
            'timeStamp' => $callback['timeStamp'],
        ];

        // 验证签名
        if ($callback['sign'] !== $this->getSignature($sign_params, $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 二次校驗
        $data = $this->doubleCheck($callback, $orderNo, $order);

        // 取出 data
        $data = $data['data'];

        // 更新订单
        $update = [
            'status' => $this->transformWithdrawStatus($callback['tradeState']),
            'trade_no' => $callback['tradeNo'],
        ];
        $this->updateOrder($orderNo, $update, 'withdraw');

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $params = $this->transferOrderInfo($data, $order);

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

        // 三方返回数据示例, data 為陣列
        // {
        //     "customer_id": 58787,
        //     "order_id": "order1582539115",
        //     "amount": "300.0000",
        //     "datetime": "2020-05-12 21:06:57",
        //     "sign": "E6144CDA4177A00ED3F6731870DD06DD",
        //     "transaction_id": "P2020051215480616131",
        //     "transaction_code": "30000",
        //     "transaction_msg": "⽀付成功
        // }

        if (0 != $response['code']) {
            $this->logger->error('TP Error: ' . json_encode($response, JSON_UNESCAPED_SLASHES));

            throw new ApiException('查單失敗 ' . $orderNo);
        }

        // 三方訂單狀態與回調訂單狀態不一致
        if ($response['data']['tradeState'] != $callback['tradeState']) {
            if ('dev' != config('app_env')) {
                throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
            }
            $this->logger->warning('訂單狀態確認失敗 ' . $orderNo);
        }

        return $response;
    }
}
