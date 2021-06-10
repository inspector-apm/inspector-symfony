<?php

namespace Inspector\Symfony\Bundle\Command;

use Inspector\Inspector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InspectorPulseCommand extends Command
{
    protected static $defaultName = 'inspector:pulse';
    protected static $defaultDescription = 'Collect server resources consumption.';

    /**  @var Inspector */
    protected $inspector;

    public function __construct(Inspector $inspector)
    {
        parent::__construct();

        $this->inspector = $inspector;
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
        if ($this->inspector->hasTransaction() && $this->inspector->isRecording()) {
            $this->inspector->currentTransaction()->sampleServerStatus(1);
        }

        return Command::SUCCESS;
    }
}
