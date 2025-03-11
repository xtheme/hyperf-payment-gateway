<?php

declare(strict_types=1);

namespace App\Payment\Drivers\GOSM;

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

    protected string $signField = 'signedMsg';

    protected string $notifySuccessText = 'Success';

    protected string $notifyFailText = 'Fail';

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
     * 查詢商戶餘額
     */
    public function balance(RequestInterface $request)
    {
        return make(Balance::class, ['config' => $this->config])->request($request);
    }

    /**
     * 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformStatus($status): string
    {
        // 三方支付状态:
        // WAITING  等待完成,
        // WAITCHECK 等待审核,
        // COMPLETED 订单完成,
        // PROCESSING 等待主动接单,
        // TIMEOUT 超时,
        // MATCHFAIL 匹配失败,
        // FAIL 订单失败,
        // REFUND 订单失败，进⾏退款
        // 集成订单状态: 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        return match ($status) {
            'WAITING', 'WAITCHECK', 'PROCESSING' => '1',
            'COMPLETED' => '2',
            'TIMEOUT' => '5',
            default => '4',
        };
    }

    /**
     * 通知三方回調結果
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
     * ============================================
     *  支付渠道共用方法
     * ============================================
     */

    /**
     * 簽名規則
     */
    protected function getSignature(array $data, string $signatureKey): string
    {
        if (isset($data[$this->signField])) {
            unset($data[$this->signField]);
        }

        // 1. 字典排序
        ksort($data);

        // 2. 排除空值欄位參與簽名
        $tempData = [];

        foreach ($data as $key => $value) {
            if ('' != $value) {
                $tempData[$key] = $value;
            }
        }

        // 3. $tempData 轉成字串
        $tempStr = urldecode(http_build_query($tempData));

        // 4. 将签名参数字串直接串上约定⾦钥MD5, 使⽤sha256做杂凑
        $hash1Result = hash('sha256', $tempStr . $signatureKey, true);

        // 5. 最后将杂凑结果字串直接串上约定⾦钥MD5, 使⽤sha3-256做杂凑取得 signedMsg资料
        $hash2Result = hash('sha3-256', $hash1Result . $signatureKey, true);

        return bin2hex($hash2Result);
    }
}
