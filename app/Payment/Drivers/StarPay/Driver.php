<?php

declare(strict_types=1);

namespace App\Payment\Drivers\StarPay;

use App\Common\Response;
use App\Payment\Contracts\DriverPaymentInterface;
use App\Payment\Contracts\DriverWithdrawInterface;
use App\Payment\Drivers\AbstractDriver;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Support\make;

class Driver extends AbstractDriver implements DriverPaymentInterface, DriverWithdrawInterface
{
    protected const array BANK_CODE_MAP = [
        'ID0001' => 'BCA',
        'ID0002' => 'BNI',
        'ID0003' => 'BRI',
        'ID0004' => 'CIMB',
        'ID0005' => 'DANA',
        'ID0007' => 'MANDIRI',
        'ID0008' => 'OCBC',
        'ID0012' => 'SHOPEEPAY',
        'ID0013' => 'GOPAY',
        'ID0014' => 'PERMATA',
        'ID0015' => 'BJB',
        'ID0016' => 'DANAMON',
        'ID0017' => 'BTN',
        'ID0018' => 'MAYBANK',
        'ID0019' => 'SINARMAS',
        'ID0020' => 'PANIN',
        'ID0021' => 'BNI_SYR',
        'ID0022' => 'MANDIRI_SYR',
        'ID0023' => 'DKI',
        'ID0024' => 'MEGA',
        'ID0025' => 'BSI',
        'ID0026' => 'BTPN',
        'ID0027' => 'BRI_SYR',
        'ID0028' => 'MUAMALAT',
    ];

    protected const array USERNAME_DEFAULT = [
        'Ahmad Putra',
        'Anisa Rahma',
        'Bambang Wibowo',
        'Budi Santoso',
        'Citra Dewi',
        'Dewa Kurniawan',
        'Dian Sari',
        'Eko Prasetyo',
        'Endah Lestari',
        'Fajar Hidayat',
        'Fitri Nuraini',
        'Gita Rahayu',
        'Hadi Wijaya',
        'Hanafi Rahman',
        'Indah Permatasari',
        'Irfan Maulana',
        'Joko Susilo',
        'Kartini Widodo',
        'Lestari Suryani',
        'Lutfi Hakim',
        'Mulyono Aditya',
        'Nanda Kurniawan',
        'Nurul Aini',
        'Putri Anjani',
        'Rahayu Andini',
        'Rahman Fadli',
        'Rani Setiawan',
        'Ridho Pratama',
        'Rina Amelia',
        'Sari Dewi',
        'Setiawan Putra',
        'Siti Zubaidah',
        'Sri Mulyani',
        'Sulaiman Idris',
        'Surya Kencana',
        'Susanto Rahman',
        'Syahrul Gunawan',
        'Taufik Hidayat',
        'Teguh Santoso',
        'Tika Sari',
        'Ujang Setiawan',
        'Wahyu Utama',
        'Widya Kusuma',
        'Wijaya Saputra',
        'Yanti Kusuma',
        'Yudi Prasetyo',
        'Zainal Abidin',
        'Zulfiqar Fadhil',
        'Aditya Pratama',
        'Agung Saputra',
        'Aisyah Putri',
        'Akbar Maulana',
        'Alif Hidayat',
        'Amalia Fitria',
        'Ananda Sari',
        'Anggi Pratama',
        'Anwar Santoso',
        'Ari Wibowo',
        'Arini Permata',
        'Bagus Pratama',
        'Bayu Nugroho',
        'Bella Sari',
        'Bintang Ramadhan',
        'Cahaya Sari',
        'Citra Ayu',
        'Dedi Kurniawan',
        'Dewi Sartika',
        'Dian Permata',
        'Dimas Saputra',
        'Farah Nuraini',
        'Ganesha Mahardika',
        'Hendra Setiawan',
        'Indriani Sari',
        'Intan Permata',
        'Iqbal Pratama',
        'Jihan Rahmawati',
        'Kiki Prasetyo',
        'Kusuma Rahman',
        'Lia Permatasari',
        'Lilis Suryani',
        'Mahendra Aditya',
        'Maya Rahayu',
        'Mega Sari',
        'Nia Rahmawati',
        'Novia Sari',
        'Oktavia Permata',
        'Putra Wijaya',
        'Rina Anggraeni',
        'Rizki Pratama',
        'Rosa Wulandari',
        'Sandi Prasetyo',
        'Santi Kurniawan',
        'Siti Hajar',
        'Sukma Dewi',
        'Wahyu Pratama',
        'Widya Sari',
        'Yudha Saputra',
        'Yulia Rahayu',
        'Yuliana Permata',
        'Zainuddin Akbar',
    ];

    /**
     * ============================================
     *  三方配置
     * ============================================
     */
    protected bool $amountToDollar = true; // 分=false 元=true

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
        // 三方支付状态: 0 待支付 1 支付成功， 2 订单失败
        // 集成订单状态: 0=失败, 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        return match ($status) {
            0 => '1',
            1 => '2',
            2 => '4',
            default => '0',
        };
    }

    /**
     * 代付: 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformWithdrawStatus($status): string
    {
        // 三方支付状态: 0 待支付，1 代付成功， 2代付失败
        // 集成订单状态: 0=預約中, 1=待處理, 2=執行中, 3=成功, 4=取消, 5=失敗, 6=審核中

        return match ($status) {
            0 => '1',
            1 => '3',
            2 => '5',
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
        // MD5(amount+callbackUrl +channelCode+merchantCode + orderNumber + md5_key)
        $tempStr = $data['amount'] . $data['callbackUrl'] . $data['channelCode'] . $data['merchantCode'] . $data['orderNumber'] . $signatureKey;

        // 6. MD5 並轉大寫
        // return strtolower(md5($tempStr));
        return md5($tempStr);
    }
}
