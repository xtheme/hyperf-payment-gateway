<?php

declare(strict_types=1);

namespace App\Common;

use Carbon\Carbon;
use Hyperf\Context\Context;

class HttpRequestPresenter
{
    private string $project = '';

    private Carbon $errorTime;

    private string $url;

    private string $requestBody;

    private int $status;

    private string $response;

    private string $tracingId;

    private string $requestHeaders;

    private string $exceptionMsg;

    private string $className;

    private function __construct()
    {
        $this->tracingId = Context::get('tracing_id');
        // $this->project = config('app_name');
        // $this->errorTime = Carbon::now(); // UTC +0
        $this->url = Context::get('tp_url');
        $this->requestHeaders = json_encode(Context::get('tp_request_headers'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $request_body = Context::get('tp_request_body');

        if (isset($request_body['raw'])) {
            $request_body['raw'] = json_decode($request_body['raw'], true);
        }
        $this->requestBody = json_encode($request_body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $this->status = Context::get('tp_status') ?? 0;
        $this->response = Context::get('tp_response') ?? '';
        $this->exceptionMsg = Context::get('tp_exception_msg') ?? '';
        $this->className = Context::get('tp_class_name') ?? '';
    }

    public static function make(): static
    {
        return new static();
    }

    public function present(): string
    {
        return <<<EOF

Class Name： {$this->className}
API： {$this->url}
Request Headers： {$this->requestHeaders}
Request Body： {$this->requestBody}
Status： {$this->status}
Response： {$this->response}
Exception Msg： {$this->exceptionMsg}
Tracing Id： {$this->tracingId}
EOF;
    }
}
