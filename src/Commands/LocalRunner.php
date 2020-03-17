<?php
declare(strict_types=1);

namespace EthicalJobs\Standards\Commands;

use EthicalJobs\Standards\ToolProcess;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class LocalRunner extends Command
{
    protected static $defaultName = 'run';

    /**
     * @var ToolProcess[]
     */
    private $tools;

    /**
     * LocalRunner constructor.
     *
     * @param ToolProcess[] $tools
     */
    public function __construct(array $tools)
    {
        $this->tools = $tools;

        parent::__construct(static::$defaultName);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $symfonyStyle = new SymfonyStyle($input, $output);
        $sections = [];

        // Start all of the tools
        foreach ($this->getToolProcesses() as $tool) {
            $tool->run();

            /** @var ConsoleSectionOutput $section */
            $sections[$tool->getName()] = $output->section();
        }

        /** @var ConsoleSectionOutput $progressSection */
        $progressSection = $output->section();
        $progressBar = new ProgressBar($progressSection);
        $progressBar->setProgressCharacter("\xF0\x9F\x8D\xBA");


        $resultingExitCodes = [];
        $runningTools = $this->getToolProcesses();
        $totalTools = count($this->getToolProcesses());
        $progressBar->setMaxSteps($totalTools);

        // Setup a 'wait' for each tool process to render to output
        while (true) {
            foreach ($runningTools as $index => $tool) {
                $section = $sections[$tool->getName()];

                // Process has finished, but exit code was not 0
                if ($tool->getProcess()->isRunning() === false) {

                    $section->overwrite($tool->getProcess()->getOutput());

                    $exitCode = $tool->getProcess()->getExitCode();
                    $resultingExitCodes[$tool->getName()] = $exitCode;

                    if ($exitCode === 0) {
                        $section->clear();
                    }

                    $progressSection->clear();
                    $progressBar->advance();

                    unset($runningTools[$index]);
                }
            }

            if (count($runningTools) === 0) {
                $progressSection->clear();
                break;
            }

            // Sleep 100ms
            usleep(100000);
        }

        $allToolsSuccessful = \array_sum($resultingExitCodes) === 0;

        if ($allToolsSuccessful === true) {
            $symfonyStyle->success(
                \sprintf('All standards passed!')
            );

            return 0;
        }

        $failed = [];
        foreach ($this->getToolProcesses() as $process) {
            if ($process->getProcess()->getExitCode() !== 0) {
                $failed[] = \basename($process->getName());
            }
        }

        $symfonyStyle->error(
            \sprintf('[%s] did not pass standards', \implode(', ', $failed))
        );
        return 255;
    }

    /**
     * @return ToolProcess[]
     */
    private function getToolProcesses(): array
    {
        return $this->tools;
    }
}
