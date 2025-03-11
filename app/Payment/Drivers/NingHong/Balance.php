<?php

declare(strict_types=1);

namespace App\Payment\Drivers\NingHong;

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

        // 取得商戶 Token
        $token = $this->getMerchantToken($input);
        $this->withToken($token);

        $response = $this->queryBalance($endpointUrl, $input);

        if (true !== $response['success']) {
            return Response::error('TP Error queryBalance', ErrorCode::ERROR, $response);
        }

        $data = $this->transferBalance($response, $input);

        return Response::success($data);
    }

    /**
     * 返回整理过的余额资讯给集成网关
     */
    public function transferBalance(array $response = [], array $input = []): array
    {
        $list = $response['balances'];

        $balance = 0;
        foreach ($list as $row) {
            if ($row['currency'] == $input['currency']) {
                $balance = $row['amount'];
                break;
            }
        }

        return [
            'payment_platform' => $input['payment_platform'],
            'balance' => $this->revertAmount($balance), // 將元轉回分
            'created_at' => $this->getServerDateTime(), // 返回集成使用 UTC
        ];
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
        // 三方接口需要的參數
        return [
            'merchant_code' => $data['merchant_id'],
        ];
    }
}