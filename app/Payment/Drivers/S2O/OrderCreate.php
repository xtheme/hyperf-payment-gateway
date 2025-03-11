<?php

declare(strict_types=1);

namespace App\Payment\Drivers\S2O;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
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

        // 三方返回数据校验
        if (200 !== $response['status']) {
            $errorMessage = $response['message'];

            return Response::error('TP Error #' . $orderNo . ' ' . $errorMessage, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['order_info']['payment_uri'], // 支付链结
            'trade_no' => $response['order_info']['order_sn'] ?? '', // 支付交易订单号
            'payee_name' => $response['order_info']['payeeName'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['order_info']['payeeBankName'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['order_info']['branchName'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['order_info']['payeeAcctNo'] ?? '', // 收款人账号
            'cashier_link' => $this->getCashierUrl($orderNo), // 收銀台網址
        ];
        $this->updateOrder($orderNo, $data);

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        // 依据三方创建代收接口规范定义请求参数
        $params = [
            'cus_code' => $data['merchant_id'], // 商戶號
            'cus_order_sn' => $orderNo, // 商戶訂單號
            'amount' => (int) $this->convertAmount($data['amount']), // 金額（元）精確到小數點兩位
            'payment_flag' => $data['payment_channel'], // 通道類型: 0=銀行轉帳(預設), 1=超商代碼, 2=虛擬帳號轉帳, 3=信用卡
            'notify_url' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址 (payment-gateway)
        ];

        // 銀行
        if ('webbk_real_name' == $data['payment_channel']) {
            if (!empty($data['bank_code'])) {
                // 有填銀行代碼的話再檢查
                if (!isset(self::BANK_CODE_MAP[$data['bank_code']])) {
                    throw new ApiException('渠道不支持此家银行代付 ' . $data['bank_code']);
                }
                $params['bank_code'] = self::BANK_CODE_MAP[$data['bank_code']];
            }

            if (!empty($data['user_name'])) {
                $params['attach_data'] = json_encode(['card_name' => $data['user_name']]);
            }
        }

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
