<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Juying;

use App\Common\Response;
use App\Constants\ErrorCode;
use App\Exception\ApiException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Stringable\Str;
use Psr\Http\Message\ResponseInterface;

class Balance extends Driver
{
    public function request(RequestInterface $request): ResponseInterface
    {
        $input = $request->all();

        // 三方接口地址
        $endpointUrl = $input['endpoint_url'] ?? '';

        $response = $this->queryBalance($endpointUrl, $input);

        // 三方返回示例
        // {
        // {"code": 0,
        // "msg": "success",
        // "data":{
        //     "mch_id": "F010AE73-BD28-451E-B36F-CE86DA807843",
        //     "quota": -6307.06,
        //     "frozen": 6020.92,
        //     "rebate": 1.8000,
        //     "isPayment": true,
        //     "isCollection": true,
        //     }
        // }

        if (0 !== $response['code']) {
            return Response::error('TP Error queryBalance', ErrorCode::ERROR, $response);
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
            'balance' => $this->revertAmount($data['data']['quota']), // 分
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
        // 產生亂碼
        $rand = str_replace('-', '', Str::uuid()->toString());

        $params = [
            'mch_id' => $input['merchant_id'],
            'nonce_str' => $rand,
            'timeStamp' => (string) $this->getTimestamp(),
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $input['merchant_key']);

        return $params;
    }
}
