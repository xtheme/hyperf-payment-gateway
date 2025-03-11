<?php

declare(strict_types=1);

namespace App\Aspect;

use App\Common\Response;
use Carbon\Carbon;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;

#[Aspect]
class CreateOrderAspect extends AbstractAspect
{
    // 要切入的類或 Trait，可以多個，亦可透過 :: 標識到具體的某個方法，透過 * 可以模糊匹配
    public array $classes = [
        'App\Payment\Drivers\*\OrderCreate::request',
    ];

    public function __construct(protected ContainerInterface $container)
    {
    }

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        try {
            // 四方調用時間點
            $paymentReceivedForthCreatedAt = Carbon::now()->getTimestampMs();

            $response = $proceedingJoinPoint->process();

            // 返回四方時間點
            $paymentResponseForthCreatedAt = Carbon::now()->getTimestampMs();

            // 改寫 response data 加入時間戳 (ms)
            /** @var ResponseInterface $response */
            $content = json_decode($response->getBody()->getContents(), true);
            $log = [
                'paymentReceivedForthCreatedAt' => $paymentReceivedForthCreatedAt,
                'paymentForwardChannelCreatedAt' => Context::get('paymentForwardChannelCreatedAt'),
                'channelResponsePaymentCreatedAt' => Context::get('channelResponsePaymentCreatedAt'),
                'paymentResponseForthCreatedAt' => $paymentResponseForthCreatedAt,
            ];
            $content['data'] = array_merge($content['data'], $log);
        } catch (\Throwable $e) {
            return Response::error($e->getMessage(), 500, $log ?? []);
        }

        return $response->withBody(new SwooleStream(json_encode($content, JSON_UNESCAPED_SLASHES)));
    }
}
