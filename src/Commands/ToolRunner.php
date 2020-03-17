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

class ToolRunner extends Command
{
    protected static $defaultName = 'run';

    /**
     * @var ToolProcess[]
     */
    private $tools;

    /**
     * ToolRunner constructor.
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

        while (true) {
            // Loop until all tools have completed execution
            foreach ($runningTools as $index => $tool) {
                $section = $sections[$tool->getName()];

                // Only when the tool process has finished running we care about the output
                if ($tool->getProcess()->isRunning() === false) {
                    // Keep a history of the resulting exit code
                    $exitCode = $tool->getProcess()->getExitCode();
                    $resultingExitCodes[$tool->getName()] = $exitCode;

                    // Show output if the exit codes indicates not-successful
                    if ($exitCode !== 0) {
                        // Update the section contents with the process output
                        $section->overwrite($tool->getProcess()->getOutput());
                    }

                    $progressBar->advance();

                    unset($runningTools[$index]);
                }
            }

            // Exit while loop when no tools are left running, and remove progress indicator
            if (count($runningTools) === 0) {
                $progressSection->clear();
                break;
            }

            // Sleep 100ms per iteration of process checks
            usleep(100000);
        }

        // Determine the overall success
        $allToolsSuccessful = \array_sum($resultingExitCodes) === 0;

        if ($allToolsSuccessful === true) {
            $symfonyStyle->success(
                \sprintf('All standards passed!')
            );

            return 0;
        }

        $failed = [];
        // Determine which tools failed
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
