<?php

declare(strict_types=1);

namespace App\Payment\Drivers\BCOTC;

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

        // {"status":1,"msg":"","response":{"merchant_id":"3503b15c-add2-45c8-af89-1f6b57cbe9d9","total_assets":"0.000","available_assets":"0.000","currency_conversion":{"total_assets":"0.000","available_assets":"0.000"}}}
        if (1 !== $response['status']) {
            return Response::error('TP Error queryBalance', ErrorCode::ERROR, $response);
        }

        $data = $response['response'];
        $formatted_data = $this->transferBalance($data, $input);

        return Response::success($formatted_data);
    }

    /**
     * 返回整理过的余额资讯给集成网关
     */
    public function transferBalance(array $data = [], array $input = []): array
    {
        $fdata = [
            'payment_platform' => $input['payment_platform'],
            'balance' => $this->revertAmount($data['currency_conversion']['available_assets']),
            'created_at' => $this->getServerDateTime(), // 返回集成使用 UTC
        ];

        $fdata['sign'] = $this->genAuthKey($fdata, $input['merchant_key']);

        return $fdata;
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
    protected function prepareQueryBalance(array $input = []): array
    {
        $params = [
            'merchant_id' => $input['merchant_id'], // 商户ID
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $input['merchant_key']);

        return $params;
    }
}
