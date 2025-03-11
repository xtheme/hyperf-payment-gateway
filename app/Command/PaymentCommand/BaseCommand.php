<?php

declare(strict_types=1);

namespace App\Command\PaymentCommand;

use Hyperf\Command\Annotation\Command;
use Hyperf\Devtool\Generator\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;

#[Command]
class BaseCommand extends GeneratorCommand
{
    protected string $target = 'order-notify';

    public function __construct()
    {
        parent::__construct('gen:payment-' . $this->target);
        $this->setDescription('创建 ' . ucfirst(\Hyperf\Stringable\Str::camel($this->target)));
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, '渠道代号'],
        ];
    }

    protected function getDefaultNamespace(): string
    {
        return 'App\\Payment\\Drivers\\';
    }

    protected function getStub(): string
    {
        return __DIR__ . '/stubs/' . $this->target . '.stub';
    }

    protected function qualifyClass(string $name): string
    {
        $namespace = $this->getDefaultNamespace();

        return $namespace . $name . '\\' . ucfirst(\Hyperf\Stringable\Str::camel($this->target));
    }
}
