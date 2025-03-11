<?php

declare(strict_types=1);

namespace App\Payment\Drivers\GOSM;

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

        // {"code":0,"message":"Success","result":{"account":"CAVY80VK","amount":0,"fronzenAmount":0,"signedMsg":"1ad330b5735669da3b82ce647709c8de0fd584815eaed2847426f3dd8dc01d45","submitTime":1717036080}}
        if (0 !== $response['code']) {
            return Response::error('TP Error queryBalance', ErrorCode::ERROR, $response);
        }

        $signParams = [
            'account' => $response['result']['account'], // 商户ID
            $this->signField => $response['result'][$this->signField],
        ];

        if (false === $this->verifySignature($signParams, $input['merchant_key'])) {
            return Response::error('balance 验证签名失败', ErrorCode::ERROR, $response);
        }

        $data = $response['result'];
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
            'balance' => $this->revertAmount($data['amount']),
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
            'account' => $input['merchant_id'], // 商户ID
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $input['merchant_key']);

        return $params;
    }
}
