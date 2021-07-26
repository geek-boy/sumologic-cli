<?php

namespace App\Command;

include_once(__DIR__.'/../../config/constants.php');

use App\Controller\QueryPathController;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class QuerySetResultsPathCommand extends Command
{
    protected static $defaultName = 'query:set-results-path';
    protected static $defaultDescription = 'Add a short description for your command';
    private $pathController;

    public function __construct(QueryPathController $qpc)
    {
        // $this->apicontroller = $apicontroller;
        $this->pathController=$controller;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('new_path', InputArgument::REQUIRED, 'Path to store all query results.')
            // ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $new_path = $input->getArgument('new_path');

        if ($new_path) {
            $io->note(sprintf('You passed an argument: %s', $new_path));
        }

        // if ($input->getOption('option1')) {
        //     // ...
        // }

        $this->pathController->printPath($new_path);

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }
}
