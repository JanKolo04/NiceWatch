<?php

declare(strict_types=1);

namespace NiceWatch\Agent\Command;

use NiceWatch\Agent\Config\Config;
use NiceWatch\Agent\Reporter\Reporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ping', description: 'Fetch /api/v1/config from the server to verify connectivity and token.')]
final class PingCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to config file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $config = Config::load($input->getOption('config'));
            $response = (new Reporter($config))->fetchConfig();
        } catch (\Throwable $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");

            return self::FAILURE;
        }

        $output->writeln('<info>Server reachable, token accepted.</info>');
        $output->writeln(json_encode($response, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
