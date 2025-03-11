<?php

declare(strict_types=1);

namespace App\Payment\Drivers\JinPay;

use App\Payment\Contracts\DriverPaymentInterface;
use App\Payment\Contracts\DriverWithdrawInterface;
use App\Payment\Drivers\AbstractDriver;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Support\make;

class Driver extends AbstractDriver implements DriverPaymentInterface, DriverWithdrawInterface
{
    protected const array BANK_CODE_MAP = [
        'ID0001' => '14',
        'ID0002' => '9',
        'ID0003' => '2',
        'ID0007' => '8',
        'ID0014' => '13',
        'ID0016' => '11',
        'ID0019' => '153',
        'ID0020' => '19',
        'ID0023' => '111',
        'ID0024' => '426',
        'ID0025' => '451',
        'ID0026' => '213',
        'ID0028' => '147',
        'ID0029' => '116',
        'ID0030' => '945',
        'ID0040' => '37',
        'ID0041' => '542',
        'ID0042' => '129',
        'ID0045' => '137',
        'ID0047' => '536',
        'ID0048' => '133',
        'ID0050' => '69',
        'ID0052' => '33',
        'ID0055' => '547',
        'ID0058' => '76',
        'ID0060' => '54',
        'ID0062' => '36',
        'ID0063' => '949',
        'ID0065' => '200',
        'ID0066' => '22',
        'ID0068' => '31',
        'ID0070' => '112',
        'ID0071' => '110',
        'ID0072' => '46',
        'ID0076' => '724',
        'ID0080' => '161',
        'ID0081' => '484',
        'ID0082' => '567',
        'ID0084' => '41',
        'ID0085' => '164',
        'ID0086' => '513',
        'ID0087' => '555',
        'ID0091' => '472',
        'ID0093' => '725',
        'ID0095' => '114',
        'ID0099' => '123',
        'ID0100' => '727',
        'ID0101' => '122',
        'ID0102' => '728',
        'ID0103' => '125',
        'ID0104' => '124',
        'ID0105' => '729',
        'ID0106' => '535',
        'ID0108' => '131',
        'ID0109' => '564',
        'ID0111' => '548',
        'ID0112' => '157',
        'ID0113' => '97',
        'ID0115' => '16',
        'ID0116' => '553',
        'ID0117' => '506',
        'ID0118' => '151',
        'ID0120' => '485',
        'ID0125' => '503',
        'ID0128' => '128',
        'ID0129' => '130',
        'ID0131' => '28',
        'ID0133' => '132',
        'ID0137' => '167',
        'ID0140' => '47',
        'ID0141' => '119',
        'ID0143' => '501',
        'ID0145' => '523',
        'ID0148' => '734',
        'ID0149' => '50',
        'ID0154' => '127',
        'ID0156' => '118',
        'ID0165' => '23',
        'ID0166' => '566',
        'ID0168' => '68',
        'ID0169' => '490',
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
    protected bool $amountToDollar = true;

    protected string $signField = 'sign';

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
     */
    public function transformStatus($status): string
    {
        // 依据三方逻辑订制状态转换规则
        // 三方支付状态: 1=审核中, 2=成功, 4=失败
        // 集成订单状态: 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        $status = intval($status);

        return match ($status) {
            1 => '1',
            2 => '2',
            default => '4',
        };
    }

    /**
     * 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformWithdrawStatus($status): string
    {
        // 依据三方逻辑订制状态转换规则
        // 三方支付状态: 1=审核中, 2=成功, 4=失败, 其他情况=出款中
        // 集成订单状态: 0=預約中, 1=待處理, 2=執行中, 3=成功, 4=取消, 5=失敗, 6=審核中
        $status = intval($status);

        return match ($status) {
            1 => '6',
            2 => '3',
            4 => '5',
            default => '2',
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
     * 代付簽名規則
     */
    protected function getSignature(array $data, string $signatureKey): string
    {
        if (isset($data[$this->signField])) {
            unset($data[$this->signField]);
        }

        // 1. 字典排序
        ksort($data);

        // 2. 排除空字串欄位參與簽名
        // $tempData = array_filter($data, fn($value) => $value !== '', ARRAY_FILTER_USE_BOTH);

        // 3. $tempData 轉成字串
        $tempStr = urldecode(http_build_query($data));

        /*
        $tempStr = '';
        foreach ($data as $key => $value) {
            $tempStr .= $key . '=' . $value . '&';
        }
        $tempStr = substr($tempStr, 0, strlen($tempStr) - 1);
        */

        // 4. $tempStr 拼接密鑰
        $tempStr .= $signatureKey;

        // 5. sign = $tempStr 進行 MD5 後轉為小写
        return strtolower(md5($tempStr));
    }

    /**
     * 代收簽名規則
     */
    protected function getWithdrawSignature(array $data, string $signatureKey): string
    {
        return $this->getSignature($data, $signatureKey);
    }
}
