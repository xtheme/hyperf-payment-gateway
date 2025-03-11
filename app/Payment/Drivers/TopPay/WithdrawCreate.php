<?php

declare(strict_types=1);

namespace App\Payment\Drivers\TopPay;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class WithdrawCreate extends Driver
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
        if ($this->isOrderExists($orderNo, 'withdraw')) {
            return Response::error('商户订单号不允许重复', ErrorCode::ERROR, ['order_no' => $orderNo]);
        }

        // 準備三方請求參數
        $params = $this->prepareOrder($orderNo, $input);

        // 三方接口地址
        $endpointUrl = $request->input('endpoint_url');

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        // {"bankCode":"008","fee":"5570","orderNum":"toppay_withdraw_19598376","description":"toppay_withdraw_19598376","platRespCode":"SUCCESS","feeType":"1","platOrderNum":"W06202407231556089095710","number":"0060011643339","money":"10000","statusMsg":"Apply","name":"Siswanto","platSign":"eGZmPZQmuJk8BhQXtIyXpO6Q4VoBKjYExEa5RWsHqHmVTpW9KvUfdpJ9LrG8z5oFJFo97X+h9eDGyfy2peW8MAiJifyVQOSTSIxFSqXxKx9Do+jqmugurr8T0joGCpLona7UGG86sdV6ITNHCF4QtW9NxPmMjHWTeSM0VXPrwgg3UhJt1rv7lJTiplVX7wwBb0l2nJnEHJ36nzDuldkGBphZbPsFDhYLKOysWaF08NPXvIaaIhHlUpjka5EsfH2RO5B1LVo885RftEbf42vQ497JEScyocRkcZ0lP7+eZNW2DzK9gbxh6SAHOHcIyvwlzmTVCTD3rKb4J7HosLIFEg==","platRespMessage":"Request success","status":0} {"request-id":"0190dece-1cc1-7298-90cd-1b46c74c864e"}
        if ('SUCCESS' !== $response['platRespCode']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input, 'withdraw');

        $data = [
            'order_no' => $orderNo, // 订单号
            'trade_no' => $response['platOrderNum'] ?? '', // 三方交易号
        ];

        // 更新訂單
        $this->updateOrder($orderNo, $data, 'withdraw');

        return Response::success($data);
    }

    /**
     * 转换三方创建订单字段
     */
    protected function prepareOrder(string $orderNo, array $data = []): array
    {
        // 有填銀行代碼的話再檢查
        if (!isset(self::BANK_CODE_MAP[$data['bank_code']])) {
            throw new ApiException('渠道不支持此家银行代付 ' . $data['bank_code']);
        }

        $params = [
            'merchantCode' => $data['merchant_id'],
            'orderType' => '0', // 订单类型
            'method' => 'Transfer',
            'orderNum' => $orderNo,
            'money' => strval(intval($this->convertAmount($data['amount']))),
            'feeType' => '1',
            'bankCode' => self::BANK_CODE_MAP[$data['bank_code']],
            'number' => $data['bank_account'],
            'name' => $data['user_name'],
            'mobile' => '08123456789',
            'email' => 'xxx@xxx.com',
            'notifyUrl' => $this->getWithdrawNotifyUrl($data['payment_platform']),
            'dateTime' => $this->getDateTime(),
            'description' => $orderNo,
            // 'service_id' => $data['payment_channel'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getWithdrawSignature($params, $data['merchant_key']);

        return $params;
    }
}
