<?php

declare(strict_types=1);

use App\Constants\ReturnFormat;
use App\Payment\Drivers;
use App\Payment\OrderDrivers;

use function Hyperf\Support\env;

/** @noinspection SpellCheckingInspection */
return [
    'payment_notify_url' => env('APP_HOST', '') . '/api/v1/payment/notify/', // 代收回調
    'withdraw_notify_url' => env('APP_HOST', '') . '/api/v1/withdraw/notify/', // 代付回調
    'return_url' => 'https://www.baidu.com', // 付款後轉跳網址 (未使用)
    'order_driver' => OrderDrivers\CacheOrder::class,
    'driver' => [
        // 梧桐支付
        'wt' => [
            'class' => Drivers\Wt\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'get',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'get',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 呂布支付
        'lvbu' => [
            'class' => Drivers\Lvbu\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 財運支付
        'caiyun' => [
            'class' => Drivers\Caiyun\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 聚富支付
        'jupay' => [
            'class' => Drivers\Jupay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 鲨鱼支付系统 https://nike.just168.vip/start/#/api_doc
        'nike' => [
            'class' => Drivers\Nike\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        'stpay' => [
            'class' => Drivers\Stpay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 蠟筆支付 (邏輯與 Stpay 相同)
        'bochuang' => [
            'class' => Drivers\Bochuang\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 螞蟻支付
        'mayi' => [
            'class' => Drivers\Mayi\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        'fservice' => [
            'class' => Drivers\Fservice\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 汇赢支付
        'huiying' => [
            'class' => Drivers\Huiying\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 旭升支付 (邏輯與 Nike 相同)
        'xusheng' => [
            'class' => Drivers\Xusheng\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 聚寶盆 https://www.showdoc.com.cn/JBP/2551029492458383
        'jbp' => [
            'class' => Drivers\Jbp\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        'laifu' => [
            'class' => Drivers\Laifu\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 星辉支付
        'xinghui' => [
            'class' => Drivers\XingHui\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 紅牛支付
        'hongniu' => [
            'class' => Drivers\Hongniu\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 雄發支付
        'xiongfa' => [
            'class' => Drivers\XiongFa\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 白云代付
        'baiyun' => [
            'class' => Drivers\BaiYun\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 四方代收代付
        'fourth' => [
            'class' => Drivers\Fourth\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 三只羊支付 http://me.sanzy.click/x_mch/src/views/dev/pay_doc/pay.html
        'sanzy' => [
            'class' => Drivers\Sanzy\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 能支付 http://sh.hundunpay2.com/doc/
        'hundunpay' => [
            'class' => Drivers\Hundunpay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 青川 (能支付)
        'qingchuan' => [
            'class' => Drivers\Hundunpay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 大家发支付 http://me.hongtu.click/x_mch/src/views/dev/pay_doc/pay.html
        'dajiafa' => [
            'class' => Drivers\Sanzy\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 宏图支付
        'hongtu' => [
            'class' => Drivers\Sanzy\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 綠界
        'ecpay' => [
            'class' => Drivers\Ecpay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Taipei',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                    'return_format' => ReturnFormat::PARAM,
                    // like this -> HandlingCharge=0&ItemName=&MerchantID=3002607&MerchantTradeNo=stt23092610560003&PaymentDate=&PaymentType=&PaymentTypeChargeFee=0&TradeAmt=0&TradeDate=&TradeNo=&TradeStatus=10200083&CheckMacValue=583A02243A10C773549D9E3A3B8F37C4F02E7C4123F118205DA1FA1FACDD5227
                ],
            ],
        ],
        // 黑石支付
        'heishi' => [
            'class' => Drivers\HeiShi\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        'jiuyuan' => [
            'class' => Drivers\JiuYuan\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 萬達支付
        'wanda' => [
            'class' => Drivers\Wanda\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 玖鼎支付
        'jiuding' => [
            'class' => Drivers\JiuDing\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 豪杰支付
        'haojie' => [
            'class' => Drivers\HaoJie\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 賺錢寶支付
        'zhuanqianbao' => [
            'class' => Drivers\ZhuanQianBao\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'get',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 宝诺支付
        'baonuo' => [
            'class' => Drivers\Baonuo\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 豆豆付
        'doudoupay' => [
            'class' => Drivers\DoudouPay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'get',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 新梧桐支付 https://www.showdoc.com.cn/fantian/2572017125088301
        'newwt' => [
            'class' => Drivers\NewWt\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // OFA Pay
        'ofapay' => [
            'class' => Drivers\OfaPay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'create_withdraw' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 信達支付.
        'xinda' => [
            'class' => Drivers\XinDa\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 聚鑫支付 (代碼與信达支付相同)
        'juxin' => [
            'class' => Drivers\XinDa\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 測試支付
        'test' => [
            'class' => Drivers\Test\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 利馬付.
        'limapay' => [
            'class' => Drivers\LiMaPay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 多吉.
        'doge' => [
            'class' => Drivers\Doge\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 兆吉亿.
        'zauziei' => [
            'class' => Drivers\ZauZiEi\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        'manhe' => [
            'class' => Drivers\ManHe\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'get',
                    'body_format' => 'form',
                ],
                'query_balance' => [
                    'method' => 'get',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 完美支付 https://text.v2-perfect-vip-channel.net/docs/v1
        'perfect' => [
            'class' => Drivers\Perfect\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // QG支付
        'qg' => [
            'class' => Drivers\Qg\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'get',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'get',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 聚陽支付
        'jyuyang' => [
            'class' => Drivers\JyuYang\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 三五.
        'threefive' => [
            'class' => Drivers\ZauZiEi\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // PT.(改接信達)
        'pt' => [
            'class' => Drivers\XinDa\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // S2O支付 https://file.notion.so/f/f/f1765ea1-08bc-40bd-9761-1f5b6f1c25a0/b3f24ff4-d73c-40a0-bec8-447c3e14309e/S2O_-_API_%E5%AF%B9%E6%8E%A5%E6%96%87%E4%BB%B6.pdf?id=e6691882-b5fd-41f9-b0cd-f5ce291f51d5&table=block&spaceId=f1765ea1-08bc-40bd-9761-1f5b6f1c25a0&expirationTimestamp=1715407200000&signature=p3sSvDVjfkU4kdCnxz5yKE1RlFe7nVLZ8cDcBjE0TWg&downloadName=S2O+-+API+%E5%AF%B9%E6%8E%A5%E6%96%87%E4%BB%B6.pdf
        's2o' => [
            'class' => Drivers\S2O\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // DMP支付 https://file.notion.so/f/f/f1765ea1-08bc-40bd-9761-1f5b6f1c25a0/66380dc7-86ad-443f-88fc-f25641e66548/DMP%E6%8E%A5%E5%8F%A3.pdf?id=cc3c6344-c652-4541-be3d-b5def88f8475&table=block&spaceId=f1765ea1-08bc-40bd-9761-1f5b6f1c25a0&expirationTimestamp=1715846400000&signature=AHMPAvCPTcGW7tj4RJhXxfqrGfBskDES_7xK7i2_wHI&downloadName=DMP%E6%8E%A5%E5%8F%A3.pdf
        'dmp' => [
            'class' => Drivers\Dmp\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 水滸支付 跟旭升支付大部分相同 https://api.shzpay.xyz/guides/2-order
        'shuihu' => [
            'class' => Drivers\ShuiHu\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // GL支付 https://gl8881688.com/api/document
        'gl' => [
            'class' => Drivers\Gl\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 大海支付 https://worldpay168.readme.io/reference/readme
        'dahai' => [
            'class' => Drivers\DaHai\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 速利
        'sulifu' => [
            'class' => Drivers\Sulifu\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 順心
        'shunsin' => [
            'class' => Drivers\ShunSin\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // water支付
        'water' => [
            'class' => Drivers\Water\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 碼上支付
        'mashang' => [
            'class' => Drivers\MaShang\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 碼上支付無收銀
        'mashangnc' => [
            'class' => Drivers\MaShang\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // et支付
        'et' => [
            'class' => Drivers\Et\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // super支付
        'superpay' => [
            'class' => Drivers\SuperPay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // zc支付
        'zcpay' => [
            'class' => Drivers\Zcpay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'get',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'get',
                    'body_format' => 'json',
                ],
            ],
        ],
        // BCOTC
        'bcotc' => [
            'class' => Drivers\BCOTC\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // BeBePay
        'bebepay' => [
            'class' => Drivers\BeBePay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 聚盈支付
        'juying' => [
            'class' => Drivers\Juying\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 全通支付
        'chyuantong' => [
            'class' => Drivers\ChyuanTong\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // (新)聚鑫支付 https://jx.nojapas.com/
        'jxpay' => [
            'class' => Drivers\Jxpay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // GOSM 支付寶
        'gosm' => [
            'class' => Drivers\GOSM\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'get',
                    'body_format' => 'form',
                ],
                'query_balance' => [
                    'method' => 'get',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 维付支付 https://wfpay0.gitbook.io/api/
        'weifu' => [
            'class' => Drivers\WeiFu\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // 長城支付
        'zhangcheng' => [
            'class' => Drivers\Zhangcheng\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // JJ 支付 https://jjpay001.gitbook.io/api/
        'jjpay' => [
            'class' => Drivers\WeiFu\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // Apollo支付
        'apollo' => [
            'class' => Drivers\Apollo\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        // PayPal REST APIs https://developer.paypal.com/api/rest/
        'paypal' => [
            'class' => Drivers\PayPal\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'get',
                    'body_format' => 'json',
                ],
            ],
        ],
        'aipay' => [
            'class' => Drivers\Aipay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'get',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'get',
                    'body_format' => 'json',
                ],
            ],
        ],
        'wanfu' => [
            'class' => Drivers\WanFu\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        'boxin' => [
            'class' => Drivers\BoXin\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                    'return_format' => 'html',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        'boxinpay' => [
            'class' => Drivers\BoXinPay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        'starpay' => [
            'class' => Drivers\StarPay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        'toppay' => [
            'class' => Drivers\TopPay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        'gspay' => [
            'class' => Drivers\GsPay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                    'return_format' => 'html',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        'cashypay' => [
            'class' => Drivers\CashyPay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        'jinpay' => [
            'class' => Drivers\JinPay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 樂力支付
        'lelipay' => [
            'class' => Drivers\Lelipay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'form',
                ],
            ],
        ],
        'krpay' => [
            'class' => Drivers\KrPay\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
        // 宁红国际 https://documenter.getpostman.com/view/12461974/2sAXjDdv1Y#b375238d-fb2e-428b-acbd-f0c217dd47ac
        'ninghong' => [
            'class' => Drivers\NingHong\Driver::class,
            'config' => [
                'timezone' => 'Asia/Shanghai',
                'create_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_order' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
                'query_balance' => [
                    'method' => 'post',
                    'body_format' => 'json',
                ],
            ],
        ],
    ],
];
