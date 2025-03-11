<?php

declare(strict_types=1);

namespace App\Payment\Drivers\JiuYuan;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OrderNotify extends OrderQuery
{
    protected string $signField = 'sign';

    /**
     * 三方回調通知, 更新訂單
     */
    public function request(RequestInterface $request): ResponseInterface
    {
        $callback = $request->all();

        // 回調請求中的商戶訂單號
        $orderNo = $callback['fxddh'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo);

        // 檢查簽名規則
        $check_params = [
            'fxstatus' => $callback['fxstatus'],
            'fxid' => $callback['fxid'],
            'fxddh' => $callback['fxddh'],
            'fxfee' => sprintf('%.2f', $callback['fxfee']), // 必須小數兩位
        ];
        $check_sign = $this->getSignature($check_params, $order['merchant_key']);

        // 验证签名
        if ($callback['fxsign'] !== $check_sign) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 二次校驗
        $order_check = $this->doubleCheck($callback, $orderNo, $order);

        if (!$order_check) {
            return Response::error('二次校验查询订单失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 查單跟回調的部分欄位不一致，改 key
        $callback['amount'] = $callback['fxfee'];
        $callback['status'] = $callback['fxstatus'];

        // 更新订单
        $update = [
            'status' => $this->transformStatus($callback['fxstatus']),
            'real_amount' => $callback['amount'],
            'trade_no' => $callback['fxorder'],
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
        //     "fxid": "104",
        //     "status": "0",
        //     "result": "SUCCESS",
        //     "amount": "50.00",
        //     "error": "",
        //     "fxorder": "jiuyuan_order23150352",
        //     "sign": "c32a71534958422791bb9dfc56f9fe4d"
        // }

        // 檢查訂單號
        if (!isset($response['fxorder']) || $callback['fxddh'] != $response['fxorder']) {
            throw new ApiException('查單失敗 ' . $orderNo);
        }

        // 沒有訂單狀態可比對

        // 檢查簽名規則
        $check_params = [
            'status' => $response['status'],
            'fxid' => $order['merchant_id'],
            'fxorder' => $response['fxorder'],
            'amount' => sprintf('%.2f', $response['amount']),                   // 必須小數兩位
        ];
        $check_sign = $this->getSignature($check_params, $order['merchant_key']);

        // 二次校驗簽名
        if ($response['sign'] != $check_sign) {
            throw new ApiException('二次验证签名失败 ' . $orderNo);
        }

        return $response;
    }
}
