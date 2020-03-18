<?php

declare(strict_types=1);

namespace EthicalJobs\Standards\Commands;

use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ToolRunner extends Command
{
    protected static $defaultName = 'run';

    /**
     * @var \EthicalJobs\Standards\ToolProcess[]
     */
    private $tools;

    /**
     * ToolRunner constructor.
     *
     * @param \EthicalJobs\Standards\ToolProcess[] $tools
     */
    public function __construct(array $tools)
    {
        $this->tools = $tools;

        parent::__construct(static::$defaultName);
    }

    public function configure(): void
    {
        $this->addArgument(
            'tools',
            InputArgument::OPTIONAL,
            'Comma-delimited whitelist of tools to run (eg. phpmd, phpcs, phpstan, phpcs'
        );
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tools = $this->determineToolsToRun($input);

        $symfonyStyle = new SymfonyStyle($input, $output);
        $sections = [];

        // Start all of the tools
        foreach ($tools as $tool) {
            $tool->run();
            $section = $output->section();

            /** @var \Symfony\Component\Console\Output\ConsoleSectionOutput $section */
            $sections[$tool->getName()] = $section;
        }

        /** @var \Symfony\Component\Console\Output\ConsoleSectionOutput $progressSection */
        $progressSection = $output->section();
        $progressBar = new ProgressBar($progressSection);
        $progressBar->setProgressCharacter("\xF0\x9F\x8D\xBA");

        $resultingExitCodes = [];
        $runningTools = $tools;
        $totalTools = count($tools);
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
                        $section->writeln(\sprintf('<info>%s</info>', \basename($tool->getName())));

                        // Update the section contents with the process output
                        $section->write($tool->getProcess()->getOutput());
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
                \sprintf(
                    '%s',
                    $totalTools === 1 ? \sprintf('%s passed', \key($tools)) : 'All standards passed!'
                )
            );

            return 0;
        }

        $failed = $succeeded = [];
        // Determine which tools failed
        foreach ($tools as $process) {
            if ($process->getProcess()->getExitCode() !== 0) {
                $failed[] = \basename($process->getName());
                continue;
            }

            $succeeded[] = \basename($process->getName());
        }

        if (\count($succeeded) > 0) {
            $symfonyStyle->note(
                \sprintf('[%s] passed standards', \implode(', ', $succeeded))
            );
        }

        $symfonyStyle->error(
            \sprintf('[%s] did not pass standards', \implode(', ', $failed))
        );
        return 255;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *
     * @return \EthicalJobs\Standards\ToolProcess[]
     */
    private function determineToolsToRun(InputInterface $input): array
    {
        if (\is_string($input->getArgument('tools')) === false) {
            return $this->getAllToolProcesses();
        }

        $allTools = $this->getAllToolProcesses();
        $tools = [];
        $whitelistedTools = \array_map('trim', \explode(',', $input->getArgument('tools')));

        foreach ($whitelistedTools as $tool) {
            if (\array_key_exists($tool, $allTools) === false) {
                throw new RuntimeException(\sprintf('Could not resolve tool \'%s\'', $tool));
            }
            $tools[$tool] = $allTools[$tool];
        }

        return $tools;
    }

    /**
     * @return \EthicalJobs\Standards\ToolProcess[]
     */
    private function getAllToolProcesses(): array
    {
        return $this->tools;
    }
}
