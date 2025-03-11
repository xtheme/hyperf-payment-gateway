<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Fservice;

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
    protected bool $amountToDollar = true;

    protected string $signField = 'fxsign';

    protected string $notifySuccessText = 'success';

    protected string $notifyFailText = 'fail';

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

        // 集成订单状态: 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        // 三方支付状态: 1=正常支付, 0=支付异常
        return match ($status) {
            '1' => '2',
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
        // 參加簽名的字段
        $fields = [
            'fxid',
            'fxddh',
            'fxfee',
            'fxnotifyurl',
        ];

        // 組合簽名
        $tempStr = '';

        foreach ($fields as $key) {
            $tempStr .= $data[$key];
        }

        // 拼接密鑰
        $tempStr .= $signatureKey;

        // MD5加密
        return md5($tempStr);
    }

    // 签名【md5(订单状态+商务号+商户订单号+支付金额 +商户秘钥)】

    /**
     * 簽名規則
     */
    protected function genQuerySignature(array $data, $signatureKey): string
    {
        // 參加簽名的字段
        $fields = [
            'fxid',
            'fxddh',
            'fxaction',
        ];

        // 組合簽名
        $tempStr = '';

        foreach ($fields as $key) {
            $tempStr .= $data[$key];
        }

        // 拼接密鑰
        $tempStr .= $signatureKey;

        // MD5加密
        return md5($tempStr);
    }

    protected function getNotifySignature(array $data, $signatureKey): string
    {
        // 參加簽名的字段
        $fields = [
            'fxstatus',
            'fxid',
            'fxddh',
            'fxfee',
        ];

        // 組合簽名
        $tempStr = '';

        foreach ($fields as $key) {
            $tempStr .= $data[$key];
        }

        // 拼接密鑰
        $tempStr .= $signatureKey;

        // MD5加密
        return md5($tempStr);
    }
}
