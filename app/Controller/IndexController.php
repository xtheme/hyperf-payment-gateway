<?php

declare(strict_types=1);

namespace App\Controller;

use App\Common\Response;
use Hyperf\HttpServer\Contract\RequestInterface;

class IndexController extends AbstractController
{
    public function index(RequestInterface $request)
    {
        return Response::success();
    }
}
