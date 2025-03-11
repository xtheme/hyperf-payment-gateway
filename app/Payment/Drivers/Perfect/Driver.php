<?php

declare(strict_types=1);

namespace App\Payment\Drivers\Perfect;

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
    protected bool $amountToDollar = true;

    protected string $signField = 'sign';

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
        return make(MockQuery::class, ['config' => $this->config])->request($orderNo);
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

    public function withdrawNotify(RequestInterface $request): ResponseInterface
    {
        return make(WithdrawNotify::class, ['config' => $this->config])->request($request);
    }

    public function withdrawQuery(RequestInterface $request): ResponseInterface
    {
        return make(WithdrawQuery::class, ['config' => $this->config])->request($request);
    }

    /**
     * [Mock] 返回三方渠道回调参数
     */
    public function mockWithdrawNotify(string $orderNo): ResponseInterface
    {
        return make(MockWithdrawNotify::class, ['config' => $this->config])->request($orderNo);
    }

    /**
     * [Mock] 返回集成网关查询订单参数
     */
    public function mockWithdrawQuery(string $orderNo): ResponseInterface
    {
        return make(MockWithdrawQuery::class, ['config' => $this->config])->request($orderNo);
    }

    /**
     * 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformStatus($status): string
    {
        $status = (string) $status;

        // 三方支付状态: 00=處理中, 01=成功, 02=代收無拒絕
        // 集成订单状态: 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        return match ($status) {
            '00' => '1',
            '01' => '2',
            default => '4',
        };
    }

    /**
     * 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformWithdrawStatus($status): string
    {
        $status = (string) $status;

        // 三方支付状态: 00=處理中, 01=成功, 02=拒絕
        // 集成订单状态: 0=預約中, 1=待處理, 2=執行中, 3=成功, 4=取消, 5=失敗, 6=審核中
        return match ($status) {
            '00' => '1',
            '01' => '3',
            default => '5',
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

        // 收到回調後，請回傳 OK
        return response()->raw($this->notifySuccessText);
    }

    public function transformPaymentError($code): string
    {
        $code = (string) $code;

        return match ($code) {
            '10001' => '加簽異常',
            '10002' => '代理未授權',
            '10003' => '支付管道異常',
            '1' => '成功',
            '-1' => '建单异常',
            '-2' => '商户订单号存在',
            '-3' => '商户订单号格式异常',
            '-4' => '金额异常',
            '-5' => '持有人银行名称必填',
            '-6' => '持有人银行代码必填',
            '-7' => '持有人银行帐号必填',
            '-8' => '付款人姓名必填',
            '-9' => '缴费内容格式异常',
            '-10' => '类型异常',
            '-11' => '缴费超商代码异常',
            '-12' => '同单多组银行资料异常',
            '-13' => '付款人电话必填',
            default => '未知错误',
        };
    }

    public function transformWithdrawError($code): string
    {
        $code = (string) $code;

        return match ($code) {
            '10001' => '加簽異常',
            '10002' => '代理未授權',
            '10003' => '支付管道異常',
            '1' => '成功',
            '-1' => '建单异常',
            '-2' => '商户订单号存在',
            '-3' => '商户订单号格式异常',
            '-4' => '金额异常',
            '-5' => '持有人银行名称必填',
            '-6' => '持有人银行代码必填',
            '-7' => '持有人银行帐号必填',
            '-8' => '商户余额不足',
            default => '未知错误',
        };
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

        $data = array_filter($data, fn ($value) => '' !== $value, ARRAY_FILTER_USE_BOTH);

        // 2. $tempData 轉成字串
        $tempStr = http_build_query($data);

        // 3. $tempStr 拼接密鑰
        $tempStr .= '&api_key=' . $signatureKey;

        // 4. MD5
        return md5($tempStr);
    }
}
