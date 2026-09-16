<?php

declare(strict_types=1);

namespace Kinoba\DeadCodeBundle\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Kinoba\DeadCodeBundle\Service\CoverageReporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'dead-code:report', description: 'Generate a dead code coverage report')]
class GenerateReportCommand extends Command
{
    private CoverageReporter $reporter;

    public function __construct(CoverageReporter $reporter)
    {
        parent::__construct();
        $this->reporter = $reporter;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $data = $this->reporter->getDashboardData();

        $io->title('Rapport de Coverage');
        $io->text(sprintf('Coverage global: %s%%', $data['coveragePercentage']));
        $io->text(sprintf('Lignes couvertes: %d/%d', $data['coveredLines'], $data['totalLines']));

        $io->section('Fichiers les moins couverts');
        foreach ($data['files'] as $file) {
            if ($file['coveragePercentage'] < 100) {
                $io->writeln(sprintf(
                    '<comment>%s: %s%% (%d/%d lignes)</comment>',
                    $file['path'],
                    $file['coveragePercentage'],
                    $file['coveredLines'],
                    $file['totalLines']
                ));
            }
        }

        return Command::SUCCESS;
    }
}
