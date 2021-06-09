<?php

namespace Inspector\Symfony\Bundle\Command;

use Inspector\Inspector;
use Inspector\Configuration;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InspectorTestCommand extends Command
{
    protected static $defaultName = 'inspector:test';
    protected static $defaultDescription = 'Send data to your Inspector dashboard.';

    /**  @var Inspector */
    protected $inspector;

    /** @var LoggerInterface */
    protected $logger;

    /** @var \Inspector\Configuration */
    protected $configuration;

    public function __construct(Inspector $inspector, LoggerInterface $logger, Configuration $configuration)
    {
        parent::__construct();

        $this->inspector = $inspector;
        $this->logger = $logger;
        $this->configuration = $configuration;
    }

    protected function configure(): void
    {
        $this
            ->setName(self::$defaultName)
            ->setDescription(self::$defaultDescription)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->inspector->isRecording()) {
            $io->warning('Inspector is not enabled');

            return Command::FAILURE;
        }

        $io->info("I'm testing your Inspector integration.");

        // Test proc_open function availability
        try {
            proc_open("", [], $pipes);
        } catch (\Throwable $exception) {
            $io->warning("❌ proc_open function disabled.");

            return Command::FAILURE;
        }

        // Check Inspector API key
        $this->inspector->addSegment(function ($segment) use ($io) {
            usleep(10 * 1000);

            !empty($this->configuration->getIngestionKey())
                ? $io->info('✅ Inspector key installed.')
                : $io->warning('❌ Inspector key not specified. Make sure you specify the INSPECTOR_INGESTION_KEY in your .env file.');

            $segment->addContext('example payload', ['key' => $this->configuration->getIngestionKey()]);
        }, 'test', 'Check Ingestion key');

        // Check Inspector is enabled
        $this->inspector->addSegment(function ($segment) use ($io) {
            usleep(10 * 1000);

            $this->configuration->isEnabled()
                ? $io->info('✅ Inspector is enabled.')
                : $io->warning('❌ Inspector is actually disabled, turn to true the `enable` field of the `inspector` config file.');

            $segment->addContext('another payload', ['enable' => $this->configuration->isEnabled()]);
        }, 'test', 'Check if Inspector is enabled');

        // Check CURL
        $this->inspector->addSegment(function ($segment) use ($io) {
            usleep(10 * 1000);

            function_exists('curl_version')
                ? $io->info('✅ CURL extension is enabled.')
                : $io->warning('❌ CURL is actually disabled so your app could not be able to send data to Inspector.');

            $segment->addContext('another payload', ['foo' => 'bar']);
        }, 'test', 'Check CURL extension');

        // Report Exception
        $this->inspector->reportException(new \Exception('First Exception detected'));
        // End the transaction
        $this->inspector->currentTransaction()
            ->setResult('success')
            ->end();

        // Logs will be reported in the transaction context.
        $this->logger->debug("Here you'll find log entries generated during the transaction.");

        /*
         * Loading demo data
         */
        $io->info('Loading demo data...');

        foreach ([1, 2, 3, 4, 5, 6] as $minutes) {
            $this->inspector->startTransaction("Other transactions")
                ->start(microtime(true) - 60*$minutes)
                ->setResult('success')
                ->end(rand(100, 200));

            $this->logger->debug("Here you'll find log entries generated during the transaction.");
        }

        $io->success('Done!');

        return Command::SUCCESS;
    }
}
