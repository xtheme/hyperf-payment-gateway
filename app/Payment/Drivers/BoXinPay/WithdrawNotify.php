<?php

declare(strict_types=1);

namespace App\Payment\Drivers\BoXinPay;

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
        // {"ErrorCode":"0","Status":"3","TradingNumber":"240617535470","MerchantNumber":"boxin_withdraw_79650766","BankName":"中國信託","BankBranch":"","BranchCode":"","BankAccount":"000000000000","AccountName":"測試","MinusPoints":"1035","Fee":"35","ActualPoints":"1000","Remark":"","TransactionDate":"2024-06-17 12:12:41","WithdrawalTime":null,"Sing":"e8f5353a7a7a08604d17677e57f6b65c"}
        $callback = $request->all();

        // 回調請求中的商戶訂單號
        $orderNo = $callback['MerchantNumber'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo, 'withdraw');

        // 三方验证签名
        $body_params = json_decode($order['body_params'], true);
        $signParams = [
            'HashKey' => $order['merchant_id'],
            'ValidateKey' => $body_params['ValidateKey'],
            'TradingNumber' => $callback['TradingNumber'],
            $this->signField => $callback['Sing'],
        ];

        if (false === $this->verifySignature($signParams, $order['merchant_key'])) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 查詢訂單二次校驗訂單狀態 (非必要)
        $this->doubleCheck($callback, $orderNo, $order);

        // 更新订单
        $update = [
            'status' => $this->transformWithdrawStatus($callback['Status']),
            'trade_no' => $callback['TradingNumber'],
        ];
        $this->updateOrder($orderNo, $update, 'withdraw');

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $callback['StatusCode'] = $callback['Status'];
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

        if ('0' !== $response['ErrorCode']) {
            $this->logger->error('TP Error: ' . json_encode($response, JSON_UNESCAPED_SLASHES));

            throw new ApiException('查單失敗 ' . $orderNo);
        }

        if ($response['Data']['StatusCode'] != $callback['Status']) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        }
    }
}
