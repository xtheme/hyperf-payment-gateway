<?php

declare(strict_types=1);

namespace App\Payment\Drivers\BaiYun;

use App\Common\Response;
use App\Exception\ApiException;
use App\Payment\Drivers\Wt\Driver;
use Carbon\Carbon;
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

        // 三方返回数据示例
        // {
        //     "error_code": "0000",
        //     "data": {
        //         "platform_id": "PF0252",
        //         "platform_channels": [
        //             {
        //                 "platform_channel_id": "PFC00000672",
        //                 "channel_name": "自有渠道代收下发(实名)",
        //                 "balance": 0,
        //                 "sign": "1a80e191ff79b7fcf47785c305cfeeac"
        //             },
        //             // ... 略
        //         ],
        //         "total": 7,
        //         "total_balance": 6909400,
        //         "request_time": 1681366117,
        //         "sign": "a9eb774ea7cf91dcb760f08c6906f61d"
        //     }
        // }

        if (isset($response['error'])) {
            return Response::error($response['error']);
        }

        // 驗證簽名
        $check_sign = md5($response['fxstatus'] . $response['fxid'] . $response['fxmoney']);

        if ($response['fxsign'] != $check_sign) {
            return Response::error('签名错误');
        }

        if ($response['fxid'] !== $input['merchant_id']) {
            return Response::error('商户号错误');
        }

        if (1 != $response['fxstatus']) {
            return Response::error('查询余额失败');
        }

        $formatted_data = $this->transferBalance($response, $input);

        return Response::success($formatted_data);
    }

    /**
     * 驗證簽名
     * 外層簽名由 platform_id, total, total_balance, request_time 構成
     */
    public function checkSignature(string $sign, array $data, string $sign_key): bool
    {
        $sign_data = $data;

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
            'balance' => (string) $data['fxmoney'], // 單位元
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
        $this->logger->info('baiyun query balance params', $params);

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
            'fxid' => $input['merchant_id'],
            'fxdate' => Carbon::now()->format('YmdHis'),
            'fxaction' => 'money',
        ];
        // 签名【md5(商务号+查询时间+商户查询动作+商户秘钥)】
        // $params['fxsign'] = $this->getSignature($params, $input['merchant_key']);
        $params['fxsign'] = md5($params['fxid'] . $params['fxdate'] . $params['fxaction'] . $input['merchant_key']);

        return $params;
    }
}
