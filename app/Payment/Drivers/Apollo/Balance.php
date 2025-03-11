<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Apollo;

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

        // {"Success":1,"Message":"","Balance":0,"Freeze":0}
        if (1 !== $response['Success']) {
            return Response::error('TP Error queryBalance', ErrorCode::ERROR, $response);
        }

        $data = $this->transferBalance($response, $input);

        return Response::success($data);
    }

    /**
     * 返回整理过的余额资讯给集成网关
     */
    public function transferBalance(array $data = [], array $input = []): array
    {
        $data = [
            'payment_platform' => $input['payment_platform'],
            'balance' => $this->revertAmount($data['Balance']), // 將元轉回分
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
            'merNo' => $data['merchant_id'],
            'datetime' => $this->getDateTime(),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $data['merchant_key']);

        return $params;
    }
}
