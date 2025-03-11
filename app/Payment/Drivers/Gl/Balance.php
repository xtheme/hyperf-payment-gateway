<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Gl;

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

        // {"status":1,"msg":"success","err_code":0,"err_msg":"","merchant_id":"MU240564","card_id_0":"CA24050744","balance_0":"0.0000","risk_0":"0.0000","avail_0":"0.0000","card_id_1":"-1","balance_1":"-1","risk_1":"-1","avail_1":"-1","card_id_2":"-1","balance_2":"-1","risk_2":"-1","avail_2":"-1","card_id_3":"-1","balance_3":"-1","risk_3":"-1","avail_3":"-1","card_id_4":"-1","balance_4":"-1","risk_4":"-1","avail_4":"-1","signature":"3458CD867242593C6494D3566B067742"}
        if (0 != $response['err_code']) {
            return Response::error('TP Error queryBalance', ErrorCode::ERROR, $response);
        }

        if (false === $this->verifySignature($response, $input['merchant_key'])) {
            return Response::error('Balance 验证签名失败', ErrorCode::ERROR);
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
            'balance' => $this->revertAmount($data['balance_0']),
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
