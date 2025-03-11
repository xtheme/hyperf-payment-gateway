<?php

declare(strict_types=1);

namespace App\Constants;

use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;

#[Constants]
class ErrorCode extends AbstractConstants
{
    /**
     * @Message("server error")
     */
    public const int SERVER_ERROR = 500;

    /**
     * @Message("success")
     */
    public const int SUCCESS = 200;

    /**
     * @Message("error")
     */
    public const int ERROR = 400;

    /**
     * @Message("not found")
     */
    public const int NOT_FOUND = 404;
}
