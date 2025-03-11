<?php

declare(strict_types=1);

namespace App\Payment\Drivers\XinDa;

use App\Common\Response;
use App\Constants\ErrorCode;
use Carbon\Carbon;
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
            // {
            //     'retcode': '0',
            //     'status': '2',
            //     'payeeName': '測試銀行',
            //     'payeeBankName': '开封宋都农村商业银行',
            //     'payee_branch_name': '測試銀行',
            //     'payeeAcctNo': '1234567778899',
            //     'rockTradeNo': '5528',
            //     'tradeNo': 'juxin_27022815',
            //     'amount': '1000000',
            //     'postScript': 'null',
            //     'link': 'https://www.db751.com/Merchant/WebBank?Token=*********&ts=1714469616528'
            // }
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            return Response::error($e->getMessage());
        }

        if ('0' !== $response['retcode']) {
            return Response::error('TP Error #' . $orderNo, ErrorCode::ERROR, $response);
        }

        // 创建订单
        $this->createOrder($orderNo, $input);

        $data = [
            'order_no' => $orderNo, // 订单号
            'link' => $response['link'], // 支付链结
            'trade_no' => $response['rockTradeNo'] ?? '', // 信达支付交易订单号
            'payee_name' => $response['payeeName'] ?? '', // 收款人姓名
            'payee_bank_name' => $response['payeeBankName'] ?? '', // 收款人开户行
            'payee_bank_branch_name' => $response['branchName'] ?? '', // 收款行分/支行
            'payee_bank_account' => $response['payeeAcctNo'] ?? '', // 收款人账号
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
            'version' => '1.6',
            'cid' => $data['merchant_id'], // 商戶號
            'tradeNo' => $orderNo, // 商户订单号
            'amount' => $this->convertAmount($data['amount']), // 单笔限额为 **100-50000 **元
            'payType' => $data['payment_channel'], // 交易银行卡，值为：17, 交易USDT，值为：18, 交易数字人民币，值为：20
            'requestTime' => Carbon::now()->format('YmdHis'), // yyyyMMddHHmmss
            'notifyUrl' => $this->getNotifyUrl($data['payment_platform']), // 支付成功後三方異步通知網址
            'returnType' => '0', // JSON格式返回
            'acctName' => $data['user_name'],
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
