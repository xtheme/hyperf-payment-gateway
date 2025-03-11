<?php

declare(strict_types=1);

namespace App\Payment\Drivers\S2O;

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

        /*
        {
            "result": "success",
            "status": 200,
            "message": "請求成功",
            "account_info": {
                "nickname": "YG8888",
                "email": "YG8888@123.com",
                "total_deposit": 0,
                "total_deduct": 0,
                "contact_person": null,
                "contact_phone": null,
                "company": null,
                "created_at": "2024-05-08T08:02:40.000000Z"
            },
            "currency_rate": []
        }
        */

        if (200 !== $response['status']) {
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
        $fdata = [
            'payment_platform' => $input['payment_platform'],
            'balance' => $this->revertAmount($data['account_info']['total_deposit']), // 代收餘額
            // 'balance' => $this->revertAmount($data['account_info']['total_deduct']), // 代付餘額
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
            'cus_code' => $input['merchant_id'], // 商户ID
            'ut' => $this->getTimestamp(), // yyyyMMddHHmmss
        ];

        // 加上签名
        $params[$this->signField] = $this->getSignature($params, $input['merchant_key']);

        return $params;
    }
}
