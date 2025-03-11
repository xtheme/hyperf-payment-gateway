<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Sulifu;

use App\Common\Response;
use App\Constants\ErrorCode;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Stringable\Str;
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

        // 创建订单
        $this->createOrder($orderNo, $input);

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // 三方返回数据校验
        if (1 !== $response['Success']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // {
        //     'Success': 1,
        //     'Message': '',
        //     'oid': '202405131539562253322042',
        //     'PayPage': 'https://win.detrapay.com/polarC787u2pg6t8/deposit/msg?IsSuccess=1&Message=done&oid=202405131539562253322042&bankAccount=Touch+GO&bankCode=222&bankName=%25E6%25BE%258E%25E6%25B9%2596%25E4%25B8%2580%25E4%25BF%25A1&branchName=TOUCH%2BGO&bankAccountName=%25E6%259C%25AA%25E5%2587%25BA%25E6%25AC%25BE-%25E5%25B7%25B2%25E8%25A3%259C%25E5%25AE%25A2%25E6%2588%25B6%25E5%2595%2586%25E9%25A4%2598&noteNo=X98Y38F&orig_money=100.00&money=100.00&lang=tw&cardNumber=&cardIndex=&pay_page_type=Default',
        //     'Params': {
        //         'bankAccount': 'Touch GO',
        //         'bankCode': '222',
        //         'bankName': '澎湖一信',
        //         'branchName': 'TOUCH GO',
        //         'bankAccountName': '未出款-已補客戶商餘',
        //         'noteNo': 'X98Y38F',
        //         'orig_money': '100.00',
        //         'money': '100.00',
        //         'pay_page_type': 'Defaull',
        //         'qrcode_url': 'https://win.detrapay.com/polarC787u2pg6t8/pc/qrCodeImg/acc_314.png',
        //         'phone_no': '1515454'
        //         }
        // }

        // 依据三方创建支付接口返回的内容调整以下返回给集成网关的字段
        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['PayPage'], // 支付网址
            'trade_no' => $response['oid'] ?? '', // 三方交易号
            // 例外字段
            // 'real_amount' => $response['Params']['money'] ?? '', // 客户实际支付金额
            'payee_name' => $response['Params']['bankAccountName'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['Params']['bankName'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['Params']['branchName'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['Params']['bankAccount"'] ?? '', // 收款人账号
            'payee_nonce' => $response['noteNo'] ?? '', // 附言
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
        $supermarketCode = '';

        if (Str::startsWith($data['payment_channel'], 'SupermarketCodePay')) {
            // SupermarketCodePay-IBON => IBON
            $supermarketCode = Str::replace('SupermarketCodePay-', '', $data['payment_channel']);
            $data['payment_channel'] = 'SupermarketCodePay';
        }

        $params = [
            'merNo' => $data['merchant_id'], // 商戶號
            'tradeNo' => $orderNo, // 商戶訂單號
            'cType' => $data['payment_channel'], // 通道類型
            'bankCode' => $data['bank_code'] ?? '', // 若无指定银行,请带入空值
            'orderAmount' => $this->convertAmount($data['amount']), // 金額（元）精確到小數點兩位
            'supermarketCode' => $supermarketCode, // 超商代码
            'playerId' => $data['user_id'] ?? '', // 会员ID, 若有传此参数,系统会将此值当作附言码
            'playerName' => $data['user_name'], // 会员姓名
            'playerPayAcc' => $data['bank_account'] ?? '', // 玩家付款帐户号码
            'playerPayBankName' => $data['bank_name'] ?? '', // 玩家付款银行名称
            'notifyUrl' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
        ];

        // 參與簽名字段, 有順序性
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
