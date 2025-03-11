<?php

declare(strict_types=1);

namespace App\Payment\Drivers\TopPay;

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

        // {"productDetail":"deposit","orderNum":"toppay_00380315","platRespCode":"SUCCESS","url":"https://openapi.doku.life/cashier/v2/?orderNum=PRE1815650587644006409","platOrderNum":"PRE1815650587644006409","payMoney":"1000000","name":"Test","platSign":"OPSOpqwcm1M7ZNOlc4YSn1UKO+f27t6UB9+43ydFLyVPvEcH85HAz6bWusB8cCvKpvCW9qcUhRRiyFehLYKrHF2DrV8r3cEjGZdioA2JP9ZMnIb4dY2NurnADngtIWb44oHoc2ooETBtf/nIKjOH+vqOh3xzBI9FcHGCbwrflkx2tp1EVZbVwoLtE2N+kHg55xCneWZzXIk4uGXxvS7kVFtkpajP4/9zKSCN4RLzDlHuDrW8QIydUFWujkuz4mFeZGskkGT+QO3Ki8hcflWqsSVG8iq0T6lh6xhNAHv4QXUJJhyquIl0S5RdyPLQHAk/XcsIYorb4Ui50vpjX7o3BQ==","platRespMessage":"Request Transaction Success","email":"xxx@xxx.com"}
        if ('SUCCESS' !== $response['platRespCode']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['url'], // 支付网址
            'trade_no' => $response['platOrderNum'] ?? '', // 三方交易号
            // 收銀台字段
            'real_amount' => $response['payMoney'] ?? '', // 客户实际支付金额
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
        // create a name.
        $str1 = self::USERNAME_DEFAULT[rand(0, count(self::USERNAME_DEFAULT) - 1)];
        $str2 = self::USERNAME_DEFAULT[rand(0, count(self::USERNAME_DEFAULT) - 1)];
        $ar1 = explode(' ', $str1);
        $ar2 = explode(' ', $str2);
        $user_name = $ar1[0] . ' ' . $ar2[1];

        $params = [
            'merchantCode' => $data['merchant_id'], // 商戶號
            'orderType' => '0', // 订单类型
            'orderNum' => $orderNo, // 商戶訂單號
            'payMoney' => intval($this->convertAmount($data['amount'])), // 付款金额（只能为整数，不能有小数
            'productDetail' => 'deposit',
            'notifyUrl' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
            'dateTime' => $this->getDateTime(), // 三方非+0時區時需做時區校正
            'expiryPeriod' => 1440,
            'name' => $user_name,
            'email' => 'xxx@xxx.com',
            'phone' => '08123456789',
            // 'payment_channel' => $data['payment_channel'], // 通道類型
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
