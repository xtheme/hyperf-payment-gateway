<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Apollo;

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

        // {"Success":1,"Message":"","oid":"202406051106161756163259","PayPage":"https://cc.inhepay.info/polarID121w9e3b7i/depositID/csr?oid=202406051106161756163259","Params":{"bankAccount":"240508005000000","bankCode":"QRIS_P","bankName":"QRIS","branchName":"-","bankAccountName":"ILHAM TOFANDHI","noteNo":"e3c3755125104c7686e3a58cb","orig_money":"50000","money":"50000","pay_page_type":"NewQRIS_P","qrcode_url":"https://cc.inhepay.info/polarID121w9e3b7i/service/getQrcode?qr_text=00020101021226650013ID.PAYDIA.WWW011893600818024050800502152405080050000000303UKE51440014ID.CO.QRIS.WWW0215ID10243233228250303UKE5204596953033605405500005802ID5911  THEBAG.ME6009TANGERANG61051511462550125e3c3755125104c7686e3a58cb07152405080050000000803api6304101B","phone_no":"85213243590"}}
        if (1 !== $response['Success']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['PayPage'], // 支付网址
            'trade_no' => $response['oid'] ?? '', // 三方交易号
            // 收銀台字段
            'real_amount' => $response['Params']['money'] ?? '', // 客户实际支付金额
            'payee_name' => $response['Params']['bankAccountName'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['Params']['bankName'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['Params']['branchName'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['Params']['bankAccount'] ?? '', // 收款人账号
            'payee_nonce' => $response['Params']['noteNo'] ?? '', // 附言
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
            'merNo' => $data['merchant_id'], // 商戶號
            'tradeNo' => $orderNo, // 商戶訂單號
            'cType' => $data['payment_channel'], // 通道類型
            'orderAmount' => floatval($this->convertAmount($data['amount'])), // 金額（元）精確到小數點兩位
            'notifyUrl' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
        ];

        $signParams = [
            'merNo' => $params['merNo'],
            'tradeNo' => $params['tradeNo'],
            'orderAmount' => $params['orderAmount'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($signParams, $data['merchant_key']);

        return $params;
    }
}
