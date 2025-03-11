<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Lelipay;

use App\Common\Response;
use App\Payment\Contracts\DriverPaymentInterface;
use App\Payment\Contracts\DriverWithdrawInterface;
use App\Payment\Drivers\AbstractDriver;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Support\make;

class Driver extends AbstractDriver implements DriverPaymentInterface, DriverWithdrawInterface
{
    /**
     * ============================================
     *  三方配置
     * ============================================
     */
    protected bool $amountToDollar = false; // 分=false 元=true

    protected string $signField = 'mac';

    protected string $notifySuccessText = 'OK';

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
        return Response::error(__METHOD__ . ' not implemented', 501);
    }

    /**
     * ============================================
     *  代付接口
     * ============================================
     */

    /**
     * 创建代付订单, 返回三方交易號
     */
    public function withdrawCreate(RequestInterface $request): ResponseInterface
    {
        return make(WithdrawCreate::class, ['config' => $this->config])->request($request);
    }

    /**
     * 三方回調通知, 更新訂單
     */
    public function withdrawNotify(RequestInterface $request): ResponseInterface
    {
        return make(WithdrawNotify::class, ['config' => $this->config])->request($request);
    }

    /**
     * 查詢訂單(交易), 返回訂單明細
     */
    public function withdrawQuery(RequestInterface $request): ResponseInterface
    {
        return make(WithdrawQuery::class, ['config' => $this->config])->request($request);
    }

    /**
     * [Mock] 返回三方渠道回调参数
     */
    public function mockWithdrawNotify(string $orderNo): ResponseInterface
    {
        return Response::error(__METHOD__ . ' not implemented', 501);
    }

    /**
     * [Mock] 返回集成网关查询订单参数
     */
    public function mockWithdrawQuery(string $orderNo): ResponseInterface
    {
        return Response::error(__METHOD__ . ' not implemented', 501);
    }

    /**
     * ============================================
     *  商戶接口
     * ============================================
     */

    /**
     * 查詢商戶餘額
     */
    public function balance(RequestInterface $request)
    {
        return make(Balance::class, ['config' => $this->config])->request($request);
    }

    /**
     * 代收: 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformStatus($status): string
    {
        // 三方支付状态: 01---处理中 10---交易成功 20---交易失败 30---其他状态
        // 集成订单状态: 0=失败, 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        return match ($status) {
            '01' => '1',
            '10' => '2',
            '20' => '4',
            default => '0',
        };
    }

    /**
     * 代付: 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformWithdrawStatus($status): string
    {
        // 三方支付状态: 0=未處理, 1=處理中, 2=已出款, 3=已駁回, 4=核實不成功, 5=餘額不足
        // 集成订单状态: 0=預約中, 1=待處理, 2=執行中, 3=成功, 4=取消, 5=失敗, 6=審核中

        return match ($status) {
            0, 1, => '2',
            2 => '3',
            default => '5',
        };
    }

    /**
     * ============================================
     *  支付渠道共用方法
     * ============================================
     */

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
     * 簽名規則
     */
    protected function getSignature(array $data, string $signatureKey): string
    {
        if (isset($data[$this->signField])) {
            unset($data[$this->signField]);
        }

        if (isset($data['sign'])) {
            unset($data['sign']);
        }

        // 1. 字典排序
        ksort($data);

        // 2. 排除空值欄位參與簽名
        $data = array_filter($data, function ($value) {
            return null !== $value;
        });

        // 3. $tempData 轉成字串
        $tempStr = http_build_query($data, '', '&', PHP_QUERY_RFC3986);

        // 4. 反轉譯字串
        $tempStr = urldecode($tempStr);

        // 5. $tempStr 拼接密鑰
        $tempStr = $tempStr . '&k=' . $signatureKey;

        $this->logger->info(sprintf('加密字段 %s', $tempStr));

        // 6. MD5
        return strtolower(md5($tempStr));
    }
}
