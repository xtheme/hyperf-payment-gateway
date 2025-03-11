<?php

declare(strict_types=1);

namespace App\Exception;

use Hyperf\Contract\ValidatorInterface;
use Hyperf\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;

class AppFormValidateException extends ValidationException
{
    public function __construct(
        public ValidatorInterface $validator,
        public ?ResponseInterface $response = null,
        public array $rules = []
    ) {
        parent::__construct($validator, $response);
        $this->code = 400;
    }
}
