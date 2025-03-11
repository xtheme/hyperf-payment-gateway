<?php

declare(strict_types=1);

namespace App\Payment\Drivers\LiMaPay;

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

        // {"status":"1","data":[{"name":"人民币","code":"CNY","money":"653.0000","dongjiemoney":"0.0000"}],"data2":"","message":"","page":""}
        if ('1' !== $response['status']) {
            return Response::error('TP Error queryBalance', ErrorCode::ERROR, $response);
        }

        $data = $this->transferBalance($response['data'], $input);

        return Response::success($data);
    }

    /**
     * 返回整理过的余额资讯给集成网关
     */
    public function transferBalance(array $data = [], array $input = []): array
    {
        $result = [
            'payment_platform' => $input['payment_platform'],
            'balance' => $this->revertAmount($data[0]['money']),
            'created_at' => $this->getServerDateTime(), // 返回集成使用 UTC
        ];

        $result['sign'] = $this->genAuthKey($result, $input['merchant_key']);

        return $result;
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
            'datetime' => $this->getDateTime(),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $input['merchant_key']);

        return $params;
    }
}
