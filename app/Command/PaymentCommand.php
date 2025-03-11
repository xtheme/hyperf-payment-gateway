<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Annotation\Command;
use Hyperf\Devtool\Generator\GeneratorCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Hyperf\Collection\collect;
use function Hyperf\Tappable\tap;

#[Command]
class PaymentCommand extends GeneratorCommand
{
    public function __construct()
    {
        parent::__construct('gen:payment');
        $this->setDescription('创建三方代收脚手架');
        $this->addUsage('Alipay 创建 Alipay 代付代码脚手架');
        $this->addUsage('Alipay -w 创建 Alipay 代付代付代码脚手架');
        $this->addOption('withdraw', 'w', InputOption::VALUE_NONE, '加入代付');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $deposit_methods = [
            'order-create',
            'order-query',
            'order-notify',
            'mock-query',
            'mock-notify',
        ];

        foreach ($deposit_methods as $method) {
            $this->call('gen:payment-' . $method, [
                'name' => $this->getNameInput(),
                '--force' => true,
            ]);
        }

        if ($this->input->getOption('withdraw')) {
            $withdraw_methods = [
                'withdraw-create',
                'withdraw-query',
                'withdraw-notify',
                'mock-withdraw-query',
                'mock-withdraw-notify',
                'balance',
            ];

            foreach ($withdraw_methods as $method) {
                $this->call('gen:payment-' . $method, [
                    'name' => $this->getNameInput(),
                    '--force' => true,
                ]);
            }
        }

        return 0;
    }

    /**
     * Call another console command.
     */
    public function call(string $command, array $arguments = []): int
    {
        $arguments['command'] = $command;

        return $this->getApplication()->find($command)->run($this->createInputFromArguments($arguments), $this->output);
    }

    /**
     * Create an input instance from the given arguments.
     */
    protected function createInputFromArguments(array $arguments): ArrayInput
    {
        return tap(new ArrayInput(array_merge($this->context(), $arguments)), function (InputInterface $input) {
            if ($input->hasParameterOption(['--no-interaction'], true)) {
                $input->setInteractive(false);
            }
        });
    }

    /**
     * Get all the context passed to the command.
     */
    protected function context(): array
    {
        return collect($this->input->getOptions())->only([
            'ansi',
            'no-ansi',
            'no-interaction',
            'quiet',
            'verbose',
        ])->filter()->mapWithKeys(function ($value, $key) {
            return ["--{$key}" => $value];
        })->all();
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, '渠道代号'],
        ];
    }

    protected function getStub(): string
    {
        if ($this->input->getOption('withdraw')) {
            return __DIR__ . '/stubs/payment-full.stub';
        }

        return __DIR__ . '/stubs/payment.stub';
    }

    protected function getDefaultNamespace(): string
    {
        return 'App\\Payment\\Drivers\\';
    }

    protected function qualifyClass(string $name): string
    {
        $namespace = $this->getDefaultNamespace();

        return $namespace . $name . '\\Driver';
    }

    protected function getNameInput(): string
    {
        $name = trim($this->input->getArgument('name'));

        return ucfirst(\Hyperf\Stringable\Str::camel($name));
    }
}
