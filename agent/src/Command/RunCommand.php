<?php

declare(strict_types=1);

namespace NiceWatch\Agent\Command;

use NiceWatch\Agent\Collector\SystemCollector;
use NiceWatch\Agent\Config\Config;
use NiceWatch\Agent\Reporter\Reporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'run', description: 'Collect metrics once and send a checkin to the NiceWatch server.')]
final class RunCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to config file (overrides default search)');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Collect and print JSON, do not send to server');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $config = Config::load($input->getOption('config'));
        } catch (\Throwable $e) {
            $output->writeln("<error>{$e->getMessage()}</error>");

            return self::FAILURE;
        }

        $snapshot = [];
        if ($config->collectorEnabled('system')) {
            $snapshot = (new SystemCollector())->collect($config->hostname());
        }

        if ($input->getOption('dry-run')) {
            $output->writeln(json_encode([
                'agent_version' => '0.1.0',
                'collected_at' => date('c'),
                'system' => $snapshot,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        try {
            $response = (new Reporter($config))->sendCheckin($snapshot);
        } catch (\Throwable $e) {
            $output->writeln("<error>Checkin failed: {$e->getMessage()}</error>");

            return self::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>OK</info> snapshot #%s, host status: %s',
            $response['snapshot_id'] ?? '?',
            $response['host_status'] ?? '?'
        ));

        return self::SUCCESS;
    }
}
