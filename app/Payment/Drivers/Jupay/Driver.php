<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Jupay;

use App\Payment\Contracts\DriverPaymentInterface;
use App\Payment\Drivers\AbstractDriver;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Support\make;

class Driver extends AbstractDriver implements DriverPaymentInterface
{
    /**
     * ============================================
     *  三方配置
     * ============================================
     */
    protected bool $amountToDollar = false;

    protected string $signField = 'sign';

    protected string $notifySuccessText = 'SUCCESS';

    protected string $notifyFailText = 'FAIL';

    /**
     * ============================================
     *  代收接口
     * ============================================
     */

    /**
     * 创建代收订单, 返回支付网址
     */
    public function orderCreate(RequestInterface $request): ResponseInterface
    {
        return make(OrderCreate::class, ['config' => $this->config])->request($request);
    }

    /**
     * 三方回調通知, 更新訂單
     */
    public function orderNotify(RequestInterface $request): ResponseInterface
    {
        return make(OrderNotify::class, ['config' => $this->config])->request($request);
    }

    /**
     * 查詢訂單(交易), 返回訂單明細
     */
    public function orderQuery(RequestInterface $request): ResponseInterface
    {
        return make(OrderQuery::class, ['config' => $this->config])->request($request);
    }

    /**
     * [Mock] 返回三方渠道回调参数
     */
    public function mockNotify(string $orderNo): ResponseInterface
    {
        return make(MockNotify::class, ['config' => $this->config])->request($orderNo);
    }

    /**
     * [Mock] 返回集成网关查询订单参数
     */
    public function mockQuery(string $orderNo): ResponseInterface
    {
        return make(MockQuery::class, ['config' => $this->config])->request($orderNo);
    }

    /**
     * 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformStatus($status): string
    {
        $status = (string) $status;

        // 三方支付状态: 0=新建订单, 1=已付款, 2=已通知商家, 3=已经结算; 状态为 1, 2, 3 都为成功
        // 集成网关订单状态: 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        return match ($status) {
            '0' => '1',
            '1', '2', '3' => '2',
            default => '4',
        };
    }

    /**
     * ============================================
     *  支付渠道共用方法
     * ============================================
     */
    public function responsePlatform(string $code = ''): ResponseInterface
    {
        // 集成网关返回
        if ('00' != $code) {
            return response()->raw($this->notifyFailText);
        }

        return response()->raw($this->notifySuccessText);
    }

    /**
     * 簽名規則
     */
    protected function getSignature(array $data, string $signatureKey): string
    {
        if (isset($data[$this->signField])) {
            unset($data[$this->signField]);
        }

        // 1. 參與簽名的欄位與順序
        $keys = ['merchant_id', 'orderid', 'paytype', 'notifyurl', 'callbackurl', 'money'];
        $tempStr = array_reduce($keys, function ($carry, $key) use ($data) {
            return $carry . ($data[$key] ?? '');
        }, '');

        // 2. $tempStr 拼接密鑰
        $tempStr .= $signatureKey;

        // 3. sign = $tempStr 進行 MD5
        return md5($tempStr);
    }
}
