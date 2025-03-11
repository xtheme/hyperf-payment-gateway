<?php

declare(strict_types=1);

namespace App\Payment\Drivers\TopPay;

use App\Payment\Contracts\DriverPaymentInterface;
use App\Payment\Contracts\DriverWithdrawInterface;
use App\Payment\Drivers\AbstractDriver;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Support\make;

class Driver extends AbstractDriver implements DriverPaymentInterface, DriverWithdrawInterface
{
    protected const array BANK_CODE_MAP = [
        'ID0001' => '014',
        'ID0002' => '009',
        'ID0003' => '002',
        'ID0004' => '022',
        'ID0005' => '10002',
        'ID0006' => '10009',
        'ID0007' => '008',
        'ID0008' => '028',
        'ID0009' => '10001',
        'ID0012' => '10008',
        'ID0013' => '10003',
        'ID0014' => '013',
        'ID0015' => '110',
        'ID0016' => '011',
        'ID0017' => '200',
        'ID0018' => '016',
        'ID0019' => '153',
        'ID0020' => '019',
        'ID0021' => '427',
        'ID0022' => '451',
        'ID0023' => '111',
        'ID0024' => '426',
        'ID0025' => '4510',
        'ID0026' => '213',
        'ID0027' => '422',
        'ID0028' => '147',
        'ID0029' => '116',
        'ID0030' => '945',
        'ID0031' => '1162',
        'ID0032' => '494',
        'ID0033' => '466',
        'ID0034' => '531',
        'ID0035' => '1163',
        'ID0036' => '061',
        'ID0037' => '610',
        'ID0038' => '987',
        'ID0039' => '020',
        'ID0040' => '037',
        'ID0041' => '542',
        'ID0042' => '129',
        'ID0043' => '459',
        'ID0044' => '040',
        'ID0045' => '558',
        'ID0046' => '525',
        'ID0047' => '536',
        'ID0048' => '133',
        'ID0049' => '425',
        'ID0050' => '069',
        'ID0051' => '1450',
        'ID0052' => '033',
        'ID0053' => '688',
        'ID0054' => '5470',
        'ID0055' => '547',
        'ID0056' => '441',
        'ID0057' => '521',
        'ID0058' => '076',
        'ID0059' => '4850',
        'ID0060' => '054',
        'ID0061' => '5590',
        'ID0062' => '9490',
        'ID0063' => '949',
        'ID0064' => '559',
        'ID0065' => '2000',
        'ID0066' => '220',
        'ID0067' => '221',
        'ID0068' => '031',
        'ID0069' => '950',
        'ID0070' => '112',
        'ID0071' => '1121',
        'ID0072' => '046',
        'ID0073' => '067',
        'ID0074' => '526',
        'ID0075' => '5230',
        'ID0076' => '778',
        'ID0077' => '699',
        'ID0078' => '087',
        'ID0079' => '562',
        'ID0080' => '161',
        'ID0081' => '484',
        'ID0082' => '567',
        'ID0083' => '2120',
        'ID0084' => '041',
        'ID0085' => '164',
        'ID0086' => '513',
        'ID0087' => '555',
        'ID0088' => '146',
        'ID0089' => '5421',
        'ID0090' => '115',
        'ID0091' => '472',
        'ID0092' => '113',
        'ID0093' => '1130',
        'ID0094' => '114',
        'ID0095' => '1140',
        'ID0096' => '1141',
        'ID0097' => '032',
        'ID0098' => '095',
        'ID0099' => '123',
        'ID0100' => '1230',
        'ID0101' => '122',
        'ID0102' => '1220',
        'ID0103' => '125',
        'ID0104' => '124',
        'ID0105' => '1240',
        'ID0106' => '535',
        'ID0107' => '121',
        'ID0108' => '131',
        'ID0109' => '5640',
        'ID0110' => '564',
        'ID0111' => '548',
        'ID0112' => '157',
        'ID0113' => '097',
        'ID0114' => '947',
        'ID0115' => '160',
        'ID0116' => '553',
        'ID0117' => '506',
        'ID0118' => '151',
        'ID0119' => '1520',
        'ID0120' => '485',
        'ID0121' => '491',
        'ID0122' => '048',
        'ID0123' => '10010',
        'ID0124' => '10006',
        'ID0125' => '503',
        'ID0126' => '583',
        'ID0127' => '128',
        'ID0128' => '1280',
        'ID0129' => '130',
        'ID0130' => '145',
        'ID0131' => '280',
        'ID0132' => '517',
        'ID0133' => '132',
        'ID0134' => '520',
        'ID0135' => '584',
        'ID0136' => '167',
        'ID0137' => '1670',
        'ID0138' => '5260',
        'ID0139' => '089',
        'ID0140' => '047',
        'ID0141' => '119',
        'ID0142' => '1190',
        'ID0143' => '5010',
        'ID0144' => '5471',
        'ID0145' => '523',
        'ID0146' => '498',
        'ID0147' => '152',
        'ID0148' => '1530',
        'ID0149' => '050',
        'ID0150' => '134',
        'ID0151' => '135',
        'ID0152' => '126',
        'ID0153' => '1260',
        'ID0154' => '127',
        'ID0155' => '118',
        'ID0156' => '1180',
        'ID0157' => '1181',
        'ID0158' => '120',
        'ID0159' => '1200',
        'ID0160' => '1201',
        'ID0161' => '117',
        'ID0162' => '1170',
        'ID0163' => '045',
        'ID0164' => '042',
        'ID0165' => '023',
        'ID0166' => '566',
        'ID0167' => '405',
        'ID0168' => '212',
        'ID0169' => '490',
        'ID0170' => '1120',
        'ID0171' => '088',
        'ID0172' => '501',
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
        // 代收状态返回string
        // 三方支付状态: INIT_ORDER=订单初始化, NO_PAY=未支付, SUCCESS=支付成功, PAY_CANCEL=撤销, PAY_ERROR=支付失败
        // 集成订单状态: 1=待付款, 2=支付成功, 3=金額調整, 4=交易失敗, 5=逾期失效
        return match ($status) {
            'INIT_ORDER', 'NO_PAY' => '1',
            'SUCCESS' => '2',
            default => '4',
        };
    }

    /**
     * 转换三方订单状态, 返回统一的状态码到集成网关
     */
    public function transformWithdrawStatus($status): string
    {
        // 代付状态返回int类型
        // 三方支付状态: 0=待处理, 1=已受理, 2=代付成功, 4=代付失败, 5=银行代付中
        // 集成订单状态: 0=預約中, 1=待處理, 2=執行中, 3=成功, 4=取消, 5=失敗, 6=審核中
        $status = intval($status);

        return match ($status) {
            0 => '0',
            1 => '1',
            2 => '3',
            default => '5',
        };
    }

    public function decryptSign(string $sign, array $order): string
    {
        $sign = base64_decode($sign);
        $body = json_decode($order['body_params'], true);
        $pub_content = file_get_contents(__DIR__ . '/' . $body['public_key']);
        $pub_key = openssl_pkey_get_public($pub_content);

        $crypto = '';

        foreach (str_split($sign, 128) as $chunk) {
            openssl_public_decrypt($chunk, $decryptData, $pub_key);
            $crypto .= $decryptData;
        }

        return $crypto;
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
        $tmpData = '';

        foreach ($data as $key => $val) {
            if ('' != $val && null != $val) {
                $tmpData .= $val;
            }
        }

        // 3. 讀取私鑰.
        $private_content = file_get_contents(__DIR__ . '/' . $signatureKey);
        $private_key = openssl_pkey_get_private($private_content);

        // 4. 加密.
        $crypto = '';

        foreach (str_split($tmpData, 117) as $chunk) {
            openssl_private_encrypt($chunk, $encryptData, $private_key);
            $crypto .= $encryptData;
        }

        return base64_encode($crypto);
    }

    /**
     * 代收簽名規則
     */
    protected function getWithdrawSignature(array $data, string $signatureKey): string
    {
        return $this->getSignature($data, $signatureKey);
    }
}
