<?php

declare(strict_types=1);

namespace App\Payment\Drivers\NewWt;

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
    protected bool $amountToDollar = true;

    protected string $signField = 'sign';

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
        return Response::error(__METHOD__ . ' not implemented', 501);
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
    public function balance(RequestInterface $request): ResponseInterface
    {
        return Response::error(__METHOD__ . ' not implemented', 501);
    }

    /**
     * 代收: 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformStatus($status): string
    {
        // 支付方订单状态: 0: 已建立 1: 等待中 2: 已完成 3: 已拒绝 4: 已取消
        // 集成订单状态: 0=失败, 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        // return (string) $status;
        return match ($status) {
            0, 1 => '1',
            2, 3 => '2',
            default => '4',
        };
    }

    /**
     * 代付: 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformWithdrawStatus($status): string
    {
        // 支付方订单状态: 0: 已建立 1: 待处理 2: 等待中 3: 已完成 4: 已拒绝 5: 已取消
        // 彙整後的订单状态: 0=預約中, 1=待處理, 2=執行中, 3=成功, 4=取消, 5=失敗, 6=審核中
        // return (string) $status;
        return match ($status) {
            0, 1 => '1',
            2 => '2',
            3 => '3',
            default => '4',
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
            return response()->json(['result' => 'fail']);
        }

        return response()->json(['error_code' => '0000']);
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

        // 2. 排除空值欄位參與簽名
        $temp_data = [];

        foreach ($data as $key => $value) {
            // if ('' != $value) {
            $temp_data[$key] = $value;
            // }
        }

        // 3. $temp_data 轉成字串
        $temp_str = urldecode(http_build_query($temp_data));

        // 4. $temp_str 拼接密鑰
        $temp_str .= '&' . $signatureKey;

        // 5. sign = $temp_str 進行 MD5 後轉為小寫
        return strtolower(md5($temp_str));
    }
}
