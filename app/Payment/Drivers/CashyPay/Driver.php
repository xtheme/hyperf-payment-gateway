<?php

declare(strict_types=1);

namespace App\Payment\Drivers\CashyPay;

use App\Payment\Contracts\DriverPaymentInterface;
use App\Payment\Contracts\DriverWithdrawInterface;
use App\Payment\Drivers\AbstractDriver;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Support\make;

class Driver extends AbstractDriver implements DriverPaymentInterface, DriverWithdrawInterface
{
    protected const array BANK_CODE_MAP = [
        'ID0001' => '1',
        'ID0003' => '3',
        'ID0004' => '6',
        'ID0005' => '257',
        'ID0007' => '4',
        'ID0008' => '10',
        'ID0009' => '261',
        'ID0012' => '262',
        'ID0013' => '144',
        'ID0014' => '11',
        'ID0016' => '7',
        'ID0018' => '17',
        'ID0019' => '12',
        'ID0020' => '15',
        'ID0021' => '145',
        'ID0022' => '13',
        'ID0023' => '121',
        'ID0024' => '9',
        'ID0025' => '151',
        'ID0026' => '117',
        'ID0027' => '22',
        'ID0028' => '20',
        'ID0029' => '32',
        'ID0031' => '73',
        'ID0032' => '131',
        'ID0033' => '44',
        'ID0036' => '14',
        'ID0040' => '27',
        'ID0041' => '34',
        'ID0042' => '45',
        'ID0043' => '35',
        'ID0044' => '127',
        'ID0047' => '29',
        'ID0048' => '42',
        'ID0051' => '101',
        'ID0052' => '126',
        'ID0056' => '5',
        'ID0057' => '23',
        'ID0058' => '119',
        'ID0060' => '140',
        'ID0062' => '143',
        'ID0064' => '76',
        'ID0065' => '25',
        'ID0066' => '28',
        'ID0069' => '16',
        'ID0070' => '71',
        'ID0071' => '72',
        'ID0072' => '122',
        'ID0073' => '102',
        'ID0074' => '109',
        'ID0076' => '104',
        'ID0079' => '91',
        'ID0080' => '92',
        'ID0081' => '8',
        'ID0082' => '142',
        'ID0084' => '19',
        'ID0085' => '132',
        'ID0086' => '107',
        'ID0087' => '81',
        'ID0090' => '46',
        'ID0091' => '80',
        'ID0092' => '70',
        'ID0093' => '69',
        'ID0095' => '75',
        'ID0096' => '79',
        'ID0097' => '134',
        'ID0098' => '103',
        'ID0099' => '64',
        'ID0100' => '65',
        'ID0101' => '41',
        'ID0102' => '40',
        'ID0103' => '253',
        'ID0104' => '66',
        'ID0105' => '67',
        'ID0106' => '110',
        'ID0107' => '33',
        'ID0108' => '30',
        'ID0109' => '43',
        'ID0111' => '96',
        'ID0112' => '77',
        'ID0113' => '139',
        'ID0115' => '130',
        'ID0116' => '90',
        'ID0117' => '146',
        'ID0118' => '54',
        'ID0120' => '94',
        'ID0121' => '82',
        'ID0122' => '123',
        'ID0125' => '83',
        'ID0127' => '53',
        'ID0130' => '36',
        'ID0131' => '85',
        'ID0132' => '108',
        'ID0133' => '47',
        'ID0134' => '78',
        'ID0140' => '99',
        'ID0143' => '95',
        'ID0145' => '84',
        'ID0146' => '106',
        'ID0148' => '21',
        'ID0149' => '100',
        'ID0153' => '51',
        'ID0154' => '52',
        'ID0157' => '57',
        'ID0158' => '60',
        'ID0160' => '61',
        'ID0161' => '255',
        'ID0162' => '55',
        'ID0163' => '129',
        'ID0165' => '18',
        'ID0166' => '111',
        'ID0167' => '93',
        'ID0168' => '39',
        'ID0169' => '105',
        'ID0172' => '124',
        'ID0173' => '37',
        'ID0174' => '38',
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

    protected string $signField = 'Sign';

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
        // 三方支付状态: PAYING=支付中, SUCCESS=成功, FAIL=失败
        // 集成订单状态: 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        return match ($status) {
            'PAYING' => '1',
            'SUCCESS' => '2',
            default => '4',
        };
    }

    /**
     * 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformWithdrawStatus($status): string
    {
        // 依据三方逻辑订制状态转换规则
        // 三方支付状态: PROCESSING=处理中, WAITING=路由中, PAYING=支付中, SUCCESS=成功, FAIL=失败, CANCEL=驳回/取消
        // 集成订单状态: 0=預約中, 1=待處理, 2=執行中, 3=成功, 4=取消, 5=失敗, 6=審核中
        return match ($status) {
            'PROCESSING', 'WAITING' => '1',
            'PAYING' => '2',
            'SUCCESS' => '3',
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

        // 1. $data 轉成json string
        $tempStr = json_encode($data);

        // 2. $tempStr 拼接密鑰
        $tempStr .= $signatureKey;

        // 3. sign = $tempStr 進行 MD5
        return md5($tempStr);
    }

    /**
     * 代收簽名規則
     */
    protected function getWithdrawSignature(array $data, string $signatureKey): string
    {
        return $this->getSignature($data, $signatureKey);
    }
}
