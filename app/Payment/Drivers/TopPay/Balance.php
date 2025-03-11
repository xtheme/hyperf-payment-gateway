<?php

declare(strict_types=1);

namespace App\Payment\Drivers\TopPay;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class Balance extends Driver
{
    public function request(RequestInterface $request): ResponseInterface
    {
        $input = $request->all();

        // 三方接口地址
        $endpointUrl = $input['endpoint_url'] ?? '';

        $response = $this->queryBalance($endpointUrl, $input);

        // {"success":true,"code":1000,"message":"SUCCESS","data":[{"mchId":"S820240719142207000053","mchName":null,"mchNo":"TP1517","country":"INDONESIANEW","currency":"IDR","balance":"0.00000000","freeze":"0.00000000","waitingSettleAmount":"0.00000000","freezeWaitingSettleAmount":"0.00000000","totalAmount":"0.00000000"}]}
        if (1000 !== $response['code']) {
            return Response::error('TP Error queryBalance', ErrorCode::ERROR, $response);
        }

        $data = $this->transferBalance($response['data'][0], $input);

        return Response::success($data);
    }

    /**
     * 返回整理过的余额资讯给集成网关
     */
    public function transferBalance(array $data = [], array $input = []): array
    {
        $data = [
            'payment_platform' => $input['payment_platform'],
            'balance' => $this->revertAmount($data['balance']), // 將元轉回分
            'created_at' => $this->getServerDateTime(), // 返回集成使用 UTC
        ];

        $data[$this->signField] = $this->genAuthKey($data, $input['merchant_key']);

        return $data;
    }

    /**
     * 查詢額度
     */
    public function queryBalance(string $endpointUrl, array $input = []): array
    {
        $params = $this->prepareQueryBalance($input);

        try {
            return $this->sendRequest($endpointUrl, $params, $this->config['query_balance']);
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }
    }

    /**
     * 转换三方查询余额字段
     */
    protected function prepareQueryBalance(array $data = []): array
    {
        $params = [
            'merchantCode' => $data['merchant_id'],
            'currency' => 'IDR',
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
