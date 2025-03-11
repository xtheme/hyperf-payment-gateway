<?php

declare(strict_types=1);

namespace App\Payment\Drivers\BoXin;

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
        // {"RtnCode":"1","RtnMessage":"交易成功","MerTradeID":"boxin_28796540","MerProductID":"Store-711","MerUserID":"boxin_28796540","PayInfo":"","Amount":"1000","PaymentDate":"2024-06-13 16:41:36","Validate":"789fb14b39b8a6b82f17b440653f7deb"}
        $callback = $request->all();

        $orderNo = $callback['MerTradeID'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo);

        $body_params = json_decode($order['body_params'], true);

        // 三方验证签名
        $chkParams = [
            'ValidateKey' => $body_params['ValidateKey'],
            'RtnCode' => $callback['RtnCode'],
            'MerTradeID' => $callback['MerTradeID'],
            'MerUserID' => $callback['MerUserID'],
        ];
        $chkSign = $this->getSignature($chkParams, '');

        if ($chkSign != $callback['Validate']) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 查詢訂單二次校驗訂單狀態
        $this->doubleCheck($callback, $orderNo, $order);

        $update = [
            'status' => $this->transformStatus('1'),
            'real_amount' => $this->revertAmount($callback['Amount']), // 三方返回的金額須轉換為 "分" 返回集成网关
            'trade_no' => $callback['MerTradeID'],
        ];
        $this->updateOrder($orderNo, $update);

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $callback['TradeState'] = '1';
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

        if (1 !== $response['RtnCode']) {
            throw new ApiException('查單失敗 ' . $orderNo);
        }

        if ('1' != $response['TradeState']) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        }
    }
}
