<?php
declare(strict_types=1);

namespace EthicalJobs\Standards;

use Symfony\Component\Process\Process;

/**
 * Wrapper process for a tool that is to be run by the standards application
 */
class ToolProcess
{
    /**
     * @var Process
     */
    private $process;

    /**
     * @var string
     */
    private $binary;


    /**
     * ToolProcess constructor.
     *
     * @param string $binary
     * @param array|null $arguments
     */
    public function __construct(string $binary, ?array $arguments = null)
    {
        $this->binary = $binary;
        $this->process = new Process(
            \array_merge(
                [$binary],
                $arguments
            ),
            \getcwd()
        );
    }

    public function getName(): string
    {
        return $this->binary;
    }

    public function getProcess(): Process
    {
        return $this->process;
    }

    /**
     * @return void
     */
    public function run(): void
    {
        $this->process->start();
    }
}