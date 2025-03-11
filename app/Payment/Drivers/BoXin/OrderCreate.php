<?php

declare(strict_types=1);

namespace App\Payment\Drivers\BoXin;

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
        $arPath = explode('-', $input['payment_channel']);
        $endpointUrl .= '/' . $arPath[0];

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        $en = 'data:text/html;base64,' . base64_encode($response['html']);

        // 创建订单
        $input['currency'] = 'TWD';
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $en, // 支付网址
            'trade_no' => $response['trade_no'] ?? '', // 三方交易号
            // 收銀台字段
            'real_amount' => $response['actual_amount'] ?? '', // 客户实际支付金额
            'payee_name' => $response['card_name'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['BankName'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['card_branch'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['VatmAccount'] ?? '', // 收款人账号
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
        /*
         endpointURL 你改從 payment channel 取，取法是
        {url 後綴}-{商品代號}，例如 711 代收我會傳 Store-711，ATM 就只有 VirAccount
         * */
        $params = [
            'HashKey' => $data['merchant_id'], // 廠商 HashKey
            'HashIV' => $data['merchant_key'], // 廠商 HashIV
            'MerTradeID' => $orderNo, // 商戶訂單號
            'MerProductID' => $data['payment_channel'], // 店家商品代號
            'MerUserID' => $orderNo, // 商戶訂單號
            'Amount' => intval($this->convertAmount($data['amount'])), // 金額（元）
            'NotifyURL' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
        ];

        $arChannel = explode('-', $data['payment_channel']);

        if (count($arChannel) > 1) {
            $params['ChoosePayment'] = $arChannel[1];
        }

        return $params;
    }
}
