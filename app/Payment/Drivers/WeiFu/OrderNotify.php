<?php

declare(strict_types=1);

namespace App\Payment\Drivers\WeiFu;

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
        // {"data":"{\"order\":{\"id\":\"3cdac67c-9d53-47ea-9b13-c7ca532570a7\",\"subject\":\"deposit\",\"total_amount\":\"1000.0\",\"notify_url\":\"https://dev-payment-gateway.hmtech-dev.com/api/v1/payment/notify/weifu\",\"return_url\":null,\"merchant_order_id\":\"weifu_81873571\",\"status\":\"completed\",\"payment_url\":\"https://mwifuswzv.com/orders/3cdac67c-9d53-47ea-9b13-c7ca532570a7?token=eyJhbGciOiJSUzI1NiJ9.eyJvcmRlcl9pZCI6IjNjZGFjNjdjLTlkNTMtNDdlYS05YjEzLWM3Y2E1MzI1NzBhNyJ9.NQ4JhW9oCAlJk2rXCR5tVlUvwuEObFTFcAupLUOJaKS-asoExhgPWFzIhaXtkNV5ohcLlhVw6TSzanUmMPWBOBlhKgwMmKz458kwsc71odlQJK4tgg7la9HV5qckbihiy2cQvTT1Wb7hVNxMj2RWl9BXE4QreeAQLJT-auoKLW9X-WWEnyxnL1SjK3ryo-2a3FIoKO5nlRvFfBM6X-VziHcH2L-j9LR_8NfVYF20JPJqTsobTWu9qZ-J6pKStZPhc-ffdSYf24Lgv8JB9-yGmapbCLHJkXiXbleO9dwASMdnQkI2bnnVTxUbZW1eJdvP-IthcFSVX_XGynNIm82ILA\",\"merchant_fee\":\"33.0\",\"confirmation_code\":\"08070\",\"remark\":null,\"bank_account\":null,\"qrcode_image_url\":null,\"qrcode_url\":null,\"payment_image_url\":null,\"supplement_orders\":[],\"supplement_orders_status\":\"none\",\"payment_info\":{\"account_name\":\"18850888279\",\"surname\":\"陈\"},\"completed_at\":\"2024-05-31T14:52:09+08:00\",\"created_at\":\"2024-05-31T14:47:09+08:00\"},\"notify_type\":\"trade_completed\"}","signature":"d1KTbKMC7UP0/aSclzivBhQOIqsgqGqos84x/qcNdyM9ICOxLUbUqBG20scVctRDLvJUFgSqoeKK+ZoFOhSQDcM0Sj6+fajSpbn1sR0XJFwNwiXTp2pUKs2+U76Aq8SorpErqB2VJcAXcN7r231OL0S4f7G2tYP8Khxbt4prKAmj+Dr04vCfYQ8ZyMTsGf2zdYTJAu/ZmGj7YARgf7IaHKHHIrsfNu2zP1mTC//cOP5rUAg5yYDJopIVwt8ECJHUAqVETUUcCnghSYypD9N0+FpV9Uvl95xbTNVCrCwCExjNwF5nhYU/BNUFzzcmivlyUgQMOtZRgNqNZmTBNjiDWA=="}
        $callback = $request->all();

        $data = json_decode($callback['data'], true);
        $orderNo = $data['order']['merchant_order_id'] ?? '';

        $this->logger->info(sprintf('%s 回調參數', $orderNo), $callback);

        // 查询订单
        $order = $this->getOrder($orderNo);

        $body = json_decode($order['body_params'], true);

        $pub_content = file_get_contents(__DIR__ . '/' . $body['public_key']);
        $pub_key = openssl_pkey_get_public($pub_content);
        $chk = openssl_verify($callback['data'], base64_decode($callback['signature']), $pub_key, OPENSSL_ALGO_SHA256);

        if (1 !== $chk) {
            return Response::error('验证签名失败', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        $update = [
            'status' => $this->transformStatus($data['order']['status']),
            'real_amount' => $this->revertAmount($data['order']['total_amount']),
            'trade_no' => $data['order']['id'],
        ];
        $this->updateOrder($orderNo, $update);

        // 回调集成网址
        $callbackUrl = $order['callback_url'];

        // 返回数据给集成网关更新
        $params = $this->transferOrderInfo($data['order'], $order);

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

        // todo 額外 header 參數
        // $this->appendHeaders($order['header_params']);

        // todo 三方訂單狀態與回調訂單狀態不一致
        if ($response['status'] != $callback['status']) {
            throw new ApiException('訂單狀態確認失敗 ' . $orderNo);
        }

        // todo 二次校驗簽名
        if (false === $this->verifySignature($response, $order['merchant_key'])) {
            throw new ApiException('二次验证签名失败 ' . $orderNo);
        }
    }
}
