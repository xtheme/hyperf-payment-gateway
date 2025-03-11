<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ChyuanTong;

use App\Common\Response;
use App\Constants\ErrorCode;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OrderCreate extends Driver
{
    /**
     * 创建代收订单, 返回支付网址
     */
    public function request(RequestInterface $request): ResponseInterface
    {
        $input = $request->all();

        // 商户订单号
        $orderNo = $input['order_id'];

        // 檢查訂單號
        if ($this->isOrderExists($orderNo)) {
            return Response::error('商户订单号不允许重复', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 准备三方请求参数
        $params = $this->prepareOrder($orderNo, $input);

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // {"message":"成功","code":"SUCCESS","data":{"redirect_url":"https://awscheckout.pcakasjt.com/8a916c13-4977-4fd9-99b4-1bb66bc7c980","order_uuid":"8a916c13-4977-4fd9-99b4-1bb66bc7c980"}}
        if ('SUCCESS' !== $response['code']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['data']['redirect_url'], // 支付网址
            'trade_no' => $response['data']['order_uuid'] ?? '', // 三方交易号
            // 例外字段
            'real_amount' => $response['actual_amount'] ?? '', // 客户实际支付金额
            'payee_name' => $response['card_name'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['card_bank'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['card_branch'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['card_number'] ?? '', // 收款人账号
            'payee_nonce' => $response['nonce'] ?? '', // 附言
            'cashier_link' => $this->getCashierUrl($orderNo), // 收銀台網址
        ];

        // 更新訂單
        $this->updateOrder($orderNo, $data);

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        $params = [
            'order_amount' => intval($this->convertAmount($data['amount'])), // 金額（元）必须是整数
            'merchant_uuid' => $data['merchant_id'], // 商戶號
            'merchant_order_id' => $orderNo, // 商戶訂單號
            'currency_type' => 1, // 币种, 1 CNY
            'payment_type' => intval($data['payment_channel']), // 1：⽹银5：快捷6：⽀付宝扫码7：⽀付宝H5
            'order_user_real_name' => $data['user_name'], // ⼤中天⽀付⽅式为 1（⽹银）时为必填
            'merchant_notify_url' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
