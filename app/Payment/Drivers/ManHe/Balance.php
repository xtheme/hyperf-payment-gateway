<?php

declare(strict_types=1);

namespace App\Payment\Drivers\ManHe;

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

        // add Token to header
        $headers = [
            'Authorization' => $input['header_params']['Authorization'] ?? '',
        ];
        $this->withHeaders($headers);

        $response = $this->queryBalance($endpointUrl, $input);

        //  {"error_code":"0000","data":{"platform_id":"PF0111","platform_channels":[{"platform_channel_id":"PFC00000262","channel_name":"自有渠道代收下发(实名)","balance":900000,"frozen_balance":0,"sign":"b63910d585f38b4bd4cfef0c1116d0c0"}],"total":1,"total_balance":900000,"total_frozen_balance":0,"request_time":1714964737,"sign":"9513021f098bd88b2354acc98b7f1efb"}}
        if ('0000' !== $response['error_code']) {
            return Response::error('TP Error!', ErrorCode::ERROR, $response);
        }

        $data = $response['data'];

        // 驗證簽名
        if (false === $this->checkSignature($data['sign'], $data, $input['merchant_key'])) {
            return Response::error('签名错误');
        }

        if ($data['platform_id'] !== $input['merchant_id']) {
            return Response::error('商户号错误');
        }

        $formatted_data = $this->transferBalance($data, $input);

        return Response::success($formatted_data);
    }

    /**
     * 驗證簽名
     * 外層簽名由 platform_id, total, total_balance, request_time 構成
     */
    public function checkSignature(string $sign, array $data, string $sign_key): bool
    {
        $sign_data = $data;
        unset($sign_data['sign'], $sign_data['platform_channels']);

        $check_sign = $this->getSignature($sign_data, $sign_key);

        if ($sign === $check_sign) {
            return true;
        }

        return false;
    }

    /**
     * 返回整理过的余额资讯给集成网关
     */
    public function transferBalance(array $data = [], array $input = []): array
    {
        $data = [
            'payment_platform' => $input['payment_platform'],
            // 'balance' => bcdiv((string) $data['total_balance'], '100', 2), // 分轉元
            'balance' => $this->revertAmount($data['total_balance']),
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
        return [];
    }
}
