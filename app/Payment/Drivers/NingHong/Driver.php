<?php

declare(strict_types=1);

namespace App\Payment\Drivers\NingHong;

use App\Exception\ApiException;
use App\Payment\Contracts\DriverPaymentInterface;
use App\Payment\Contracts\DriverWithdrawInterface;
use App\Payment\Drivers\AbstractDriver;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Stringable\Str;
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

    protected string $notifySuccessText = 'success';

    protected string $notifyFailText = 'fail';

    protected const array BANK_CODE_MAP = [
        'ID0001' => [
            'code' => 'BCA',
            'name' => 'BCA',
        ],
        'ID0002' => [
            'code' => 'BNI',
            'name' => 'BNI',
        ],
        'ID0003' => [
            'code' => 'BRI',
            'name' => 'BRI',
        ],
        'ID0004' => [
            'code' => 'CIMB',
            'name' => 'CIMB NIAGA',
        ],
        'ID0008' => [
            'code' => 'OCBC',
            'name' => 'OCBCNISP',
        ],
        'ID0014' => [
            'code' => 'PERMATA',
            'name' => 'PERMATA',
        ],
        'ID0016' => [
            'code' => 'DANAMON',
            'name' => 'DANAMON',
        ],
        'ID0017' => [
            'code' => 'BTN',
            'name' => 'BTN',
        ],
        'ID0018' => [
            'code' => 'MBI',
            'name' => 'MAYBANK (D/H BII)',
        ],
        'ID0020' => [
            'code' => 'PANIN',
            'name' => 'PANIN',
        ],
        'ID0022' => [
            'code' => 'MIB',
            'name' => 'MANDIRI',
        ],
        'ID0024' => [
            'code' => 'MEGA',
            'name' => 'MEGA',
        ],
        'ID0025' => [
            'code' => 'BSI',
            'name' => 'BSI',
        ],
        'ID0041' => [
            'code' => 'JAGO',
            'name' => 'BANK JAGO',
        ],
        'ID0056' => [
            'code' => 'BUKOPIN',
            'name' => 'KB BUKOPIN',
        ],
        'ID0068' => [
            'code' => 'CITI',
            'name' => 'CITIBANK',
        ],
        'ID0072' => [
            'code' => 'DBS',
            'name' => 'BANK DBS',
        ],
        'ID0082' => [
            'code' => 'ALLO',
            'name' => 'ALLO BANK',
        ],
        'ID0084' => [
            'code' => 'HSBC',
            'name' => 'HSBC',
        ],
        'ID0113' => [
            'code' => 'MAYAPADA',
            'name' => 'MAYAPADA',
        ],
        'ID0165' => [
            'code' => 'UOB',
            'name' => 'UOB',
        ],
    ];

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
     * 查詢商戶餘額
     */
    public function balance(RequestInterface $request)
    {
        return make(Balance::class, ['config' => $this->config])->request($request);
    }

    /**
     * 转换三方订单状态, 返回统一的状态码到集成网关
     * https://documenter.getpostman.com/view/12461974/2sAXjDdv1Y#b375238d-fb2e-428b-acbd-f0c217dd47ac
     */
    public function transformStatus($status): string
    {
        $status = strtolower($status);

        // 依据三方逻辑订制状态转换规则
        // 三方支付状态: Pending, Success or in Rejected.
        // 集成订单状态: 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        return match ($status) {
            'pending' => '1',
            'success' => '2',
            default => '4',
        };
    }

    /**
     * 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformWithdrawStatus($status): string
    {
        $status = strtolower($status);

        // 依据三方逻辑订制状态转换规则
        // 三方支付状态: Pending, Success or in Rejected.
        // 集成订单状态: 0=預約中, 1=待處理, 2=執行中, 3=成功, 4=取消, 5=失敗, 6=審核中
        return match ($status) {
            'pending' => '1',
            'success' => '3',
            default => '5',
        };
    }

    /**
     * ============================================
     *  支付渠道共用方法
     * ============================================
     */

    /**
     * 代付簽名規則, 寧紅支付走 Bearer Token 驗證
     */
    protected function getSignature(array $data, string $signatureKey): string
    {
        return '';
    }

    /**
     * 代收簽名規則, 寧紅支付走 Bearer Token 驗證
     */
    protected function getWithdrawSignature(array $data, string $signatureKey): string
    {
        return $this->getSignature($data, $signatureKey);
    }

    /**
     * 通知三方回調結果
     */
    public function responsePlatform(string $code = ''): ResponseInterface
    {
        // 集成网关返回
        if ($code != '00') {
            // 依据三方逻辑订制回调失败返回内容
            return response()->raw($this->notifyFailText);
        }
        // 依据三方逻辑订制回调成功返回内容
        return response()->raw($this->notifySuccessText);
    }

    /**
     * 取得商戶 Bearer Token
     */
    public function getMerchantToken(array $data): string
    {
        // https://app.ninghong.org/api/v1/payin => https://app.ninghong.org/api/v1/login
        $last = basename($data['endpoint_url']);
        $endpointUrl = Str::replaceLast($last, 'login', $data['endpoint_url']);

        $params = [
            'merchant_code' => $data['merchant_id'],
            'hash' => md5($data['merchant_id'] . $data['merchant_key']),
        ];

        try {
            $response = $this->sendRequest($endpointUrl, $params, $this->config['create_order']);
        } catch (\Exception $e) {
            throw new ApiException($e->getMessage());
        }

        return $response['token'];
    }
}