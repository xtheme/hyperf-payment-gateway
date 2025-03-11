<?php

declare(strict_types=1);

namespace App\Payment\Drivers\JiuDing;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\Collection\Arr;
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
        $orderNo = $callback['outTradeId'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo);

        // 檢查簽名規則
        $check_sign = $this->getSignature(Arr::except($callback, 'callbackUrl'), $order['merchant_key']);

        // 验证签名
        if ($callback[$this->signField] !== $check_sign) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 二次校驗
        $order_check = $this->doubleCheck($callback, $orderNo, $order);

        if (!$order_check) {
            return Response::error('二次校验查询订单失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 更新订单
        $update = [
            'status' => $this->transformStatus($callback['returnCode']),
            'real_amount' => $callback['actualAmount'] ?: $callback['amount'],
            'trade_no' => $callback['outTradeId'],
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
    private function doubleCheck(array $callback, string $orderNo, array $order): array
    {
        if (empty($order['query_url'])) {
            return [];
        }

        // 验签成功, 调用第三方查询接口判断该订单在第三方系统里是否支付成功
        $response = $this->queryOrderInfo($order['query_url'], $order);

        // 三方回調 $callback
        // {
        //     "fxid": "104",
        //     "fxddh": "jiuyuan_2310021634hq045d",
        //     "fxdesc": "91001",
        //     "fxorder": "15p2023100216391386732",
        //     "fxfee": "50.00",
        //     "fxattch": "",
        //     "fxusername": "",
        //     "fxtime": "1696235953",
        //     "fxstatus": "1",
        //     "fxsign": "0e673b3a93559311ccae4390a2b16d79"
        // }

        // 三方返回数据示例
        // {
        //   "code":200,  //指的是查询成功
        //   "msg":"success",//指的是查询成功
        //   "data":{
        //     "amount":300000,   //订单金额
        //     "actualAmount":300000, //实付金额
        //     "applyDate":"1612277527", //时间戳
        //     "channelCode":"YHK", //通道
        //     "currency":"CNY", //币种
        //     "orderId":"D0202225207542390", //系统订单
        //     "merId":"190461832",//商户号
        //     "outTradeId":"E1612277527",//商户订单
        //     "orderStatus":"success",//订单状态
        //     "returnCode":"200", //已支付  400待支付 500已驳回
        //     "msg":"已支付","attach":"123456"}
        // }

        // 檢查訂單號
        if (!isset($response['data']['outTradeId']) || $callback['outTradeId'] != $response['data']['outTradeId']) {
            throw new ApiException('查單失敗 ' . $orderNo);
        }

        return $response;
    }
}
