<?php

declare(strict_types=1);

namespace App\Payment\Drivers\StarPay;

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

        // 三方返回数据校验
        if ('000' !== $response['code']) {
            return Response::error('TP Error # StarPay', ErrorCode::ERROR, $response);
        }

        $data = $response;
        $formatted_data = $this->transferBalance($data, $input);

        return Response::success($formatted_data);
    }

    /**
     * 返回整理过的余额资讯给集成网关
     */
    public function transferBalance(array $data = [], array $input = []): array
    {
        $data = [
            'payment_platform' => $input['payment_platform'],
            'balance' => $this->revertAmount(strval($data['canUseAmount'])), // 分
            'created_at' => $this->getServerDateTime(), // 返回集成使用 UTC
        ];

        $data['sign'] = $this->genAuthKey($data, $input['merchant_key']);

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
    protected function prepareQueryBalance(array $input = []): array
    {
        $params = [
            'merchantCode' => $input['merchant_id'], // 商戶號
            'timestamp' => $this->getTimestamp(), // 三方非+0時區時需做時區校正
        ];

        $sha256Key = $input['body_params']['sha256Key'];
        $tempStr = $params['merchantCode'] . $params['timestamp'] . $sha256Key;
        $sign = hash('sha256', $tempStr);

        // 加上签名
        $params[$this->signField] = $sign;

        return $params;
    }
}
