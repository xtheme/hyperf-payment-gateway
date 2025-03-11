<?php

declare(strict_types=1);

namespace App\Constants;

use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;

#[Constants]
class ReturnFormat extends AbstractConstants
{
    /**
     * @Message("json")
     */
    public const int JSON = 1;

    /**
     * @Message("param")
     */
    public const int PARAM = 2;
}
