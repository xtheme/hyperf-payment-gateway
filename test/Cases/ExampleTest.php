<?php

declare(strict_types=1);

namespace HyperfTest\Cases;

use HyperfTest\UnitTestCase;

class ExampleTest extends UnitTestCase
{
    public function testA()
    {
        $signatureKey = 'omBrVNuhkXURMSQyxbVLFyYkqoSCapzF';

        $array = [
            'fxstatus' => '1',
            'fxid' => '104',
            'fxddh' => 'jiuyuan_order72453628',
            'fxfee' => '50.00',
        ];

        // 1. 依據組合傳參拼接字串
        $tempStr = urldecode(http_build_query($array));

        // 2. $tempStr 拼接 key
        $tempStr = $tempStr . '&' . $signatureKey;
        // 3. md5
        $sign = md5($tempStr);

        dump($sign);
    }
}
