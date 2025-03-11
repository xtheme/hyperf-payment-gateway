<?php

declare(strict_types=1);

namespace App\Payment\Drivers\OfaPay;

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
        // todo 请依据三方逻辑订制状态转换规则
        // (范本) 三方支付状态: 0=處理中, 1=交易成功, -1=交易或請求失敗
        // 集成订单状态: 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        return match ($status) {
            '0' => '1',
            '1' => '2',
            default => '4',
        };
    }

    /**
     * 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformWithdrawStatus($status): string
    {
        // todo 三方支付状态: I=数据处理中, S=出金成功, F=出金失败
        // 集成订单状态: 0=預約中, 1=待處理, 2=執行中, 3=成功, 4=取消, 5=失敗, 6=審核中
        return match ($status) {
            'I' => '2',
            'S' => '3',
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
            // todo 请依据三方逻辑订制回调失败返回内容
            return response()->raw($this->notifyFailText);
        }

        // todo 请依据三方逻辑订制回调成功返回内容
        return response()->raw($this->notifySuccessText);
    }

    public function transformPaymentError($code): string
    {
        return match ($code) {
            '01' => '查无交易资料',
            '10' => '未完成支付交易',
            '11' => '传入参数错误',
            '12' => '商户号不存在或已停用',
            '13' => '支付方式未启用',
            '14' => '签名验证错误',
            '15' => '交易序号重覆',
            '16' => '商户号未设定',
            '17' => '初始交易系统错误',
            '18' => '渠道交易失败',
            '19' => '系统失败',
            '20' => '交易失败',
            '21' => '查询错误',
            default => '未知错误',
        };
    }

    public function transformWithdrawError($code): string
    {
        return match ($code) {
            '10' => '代付处理中',
            '11' => '传入参数错误',
            '12' => '商户号不存在或已停用',
            '13' => '余额不足',
            '14' => '验证码错误',
            '15' => '系统处理错误',
            '16' => '代付请求失败',
            '17' => '代付不符限额规则',
            '18' => '支付商户未设定',
            '19' => '查无代付资料',
            '31' => '代付序号重复',
            '32' => '代付数据处理失败',
            default => '未知错误',
        };
    }

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

        // 2. $tempData 轉成字串
        $tempStr = urldecode(http_build_query($data));

        // 3. $tempStr 拼接密鑰
        $tempStr .= '&key=' . $signatureKey;

        // 4. MD5
        return md5($tempStr);
    }
}
