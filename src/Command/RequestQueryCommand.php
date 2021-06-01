<?php
// src/Command/ManageOrganizationsCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use DateTime;

use App\Controller\ApiController;

class RequestQueryCommand extends Command
{
    private $apicontroller;
    private $organization_select_uuids;
    private $organization_select_names;
    
    // Default command name
    protected static $defaultName = 'query';

    public function __construct(ApiController $apicontroller)
    {
        $this->apicontroller = $apicontroller;
        parent::__construct();
    }

    protected function configure()
    {
      $this
        // the short description shown while running "php bin/console list"
        ->setDescription('Create a Query.')

        // the full command description shown when running the command with
        // the "--help" option
        ->setHelp('This command makes a request to the Sumologic Job Search API to create a query.')

        //
        ->addArgument('query_file_path', InputArgument::REQUIRED, 'The path to the file containing the Sumologic query you wish to run.')
        ->addArgument('start_time', InputArgument::REQUIRED, 'The start time for the Query in ISO Date format. Example - 2010-01-28T15:00:00')
        ->addArgument('end_time', InputArgument::REQUIRED, 'The end time for the Query in ISO Date format. Example - 2010-01-28T15:30:00')
      ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $start_time = $input->getArgument('start_time');
        if(!($this->isDateFormatCorrect($start_time, $output)))
        {
            $output->writeln("Incorrect format for start time, it should in ISO date. Example - 2010-01-28T15:00:00");
            return Command::FAILURE;
        }

        $end_time = $input->getArgument('end_time');
         if(!($this->isDateFormatCorrect($end_time, $output)))
         {
            $output->writeln("Incorrect format for end time, it should in ISO date. Example - 2010-01-28T15:00:00");
            return Command::FAILURE;
         }     

        $query_file = $input->getArgument('query_file_path');
        $fsObject = new Filesystem();
        if (!$fsObject->exists($query_file)) {
            $output->writeln("Sorry - the query file does not exist!\nPlease provide the path to your query file.");
            return Command::FAILURE;
        }
        
        $query = null;
        if(($query = file_get_contents($query_file)) === false) {
            $output->writeln("Unable to read query file :(");
            return Command::FAILURE;
        }
        
        $output->writeln('Making request to Sumologic Jobs for Query :' . PHP_EOL);
        $output->writeln($query. PHP_EOL);

        

        // Clean up formatting so as to pass this properly to API end
        $query = str_replace('"','\"',$query);
        $query = str_replace("\n", '', $query);


        /** ToDo validate $query */            
        $json_query = '{"query" :"'. $query . '",' . 
                    '"from": "' . $start_time . '",' . 
                    '"to": "' . $end_time . '",' . 
                    '"timeZone": "UTC",' . 
                    '"byReceiptTime": false' . 
                    '}';

        $response = $this->apicontroller->createSearchJob($json_query);

        $job_id=null;
        switch($response['status_code']) {
            case 202:
                $job_id = $this->getQueryJobID($response['body']->link->href);
                break;
                case 400:
                    $output->writeln("Oh Oh! Bad request - check the query format.");
                    $output->writeln("Status Code: " . $response['status_code']);
                    $output->writeln($response['reason']);
                    return Command::FAILURE;
                    break;
                default:
                    $output->writeln("Unknown response from Sumologic API - Exiting");
                    $output->writeln("Status Code: " . $response['status_code']);
                    $output->writeln($response['reason']);
                    return Command::FAILURE;
        }

        if($job_id === null) {
            $output->writeln("Error retrieving Sumologic Job ID - Exiting");
            return Command::FAILURE;
        }

        $output->writeln("Success!\nQuery is running as JOB ID: " . $job_id);

        $is_results_ready = false;
        $delay = 2; // Amount of seconds before making a new request
        $result_count = -1; // Initialise to -1 as '0' is a valid result
        while(!$is_results_ready) {
            $response = $this->apicontroller->getSearchJobStatus($job_id);

            switch($response['status_code']) {
                case 200:
                    if($response['body']->state == "DONE GATHERING RESULTS") {
                        $result_count = $response['body']->messageCount;
                        $is_results_ready = true;
                    }
                    break;
                default:
                    $output->writeln("Unknown response from Sumologic API - Exiting");
                    $output->writeln('Status code: ' .$response['status_code']);
                    $output->writeln($response['reason']);
                    return Command::FAILURE;
            }
            if($is_results_ready) {
                break;
            }
            sleep($delay);
        }
    
        if($result_count == -1) {
            $output->writeln("Unknown failure for results - Exiting");            
            return Command::FAILURE;
        } else if($result_count == 0) {
            $output->writeln("Query returned no results :(");            
            $output->writeln("You may need to check your timeframes or your query.");            
            return Command::SUCCESS;
        }

        $output->writeln('');
        $output->writeln("Query is ready!");
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('There are ' . $result_count . ' records.' . ' Do you want to download these ?', false);

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln("Ok! No results have been retrieved!");

            return Command::SUCCESS;
        }

        $output->writeln('');
        $output->writeln("Grabbing results ...");
        $save_to_path = null;
        $result = $this->saveQueryResults($output,$job_id,$result_count,0,10000);
        $save_to_path = $result['file_path'];
        $is_polaris = $result['is_polaris'];
        if(empty($save_to_path)) {
            $output->writeln("Error saving Results :(");

            return Command::FAILURE;
        }
        $output->writeln('');
        $output->writeln("Results saved to " . $save_to_path);
        $output->writeln("Results are in json format.");
        $output->writeln('');
        $output->writeln("To view the results you could use the following shell command:");
        if($is_polaris > 0) {
            $output->writeln("cat  " . $save_to_path . " | jq '.[].map | { \"timestamp\", \"namespace_name\", \"kubernetes.labels.app\",\"kubernetes.container_name\",\"log\"}' | less");
        } else {
            $output->writeln("cat  " . $save_to_path . " | jq '.[].map | { \"isodate\", \"namespace\", \"msg\"}' | less");
        }
        $output->writeln('');

        return Command::SUCCESS;
    }

    function getQueryJobID($href) {
        $pattern = str_replace("/","\/",SUMOLOGIC_JOB_SEARCH_API);
        $pattern = '/' . $pattern . '\/'. '([0-9A-Za-z]+)'.'/';
        preg_match($pattern, $href, $matches);

        if(count($matches) == 2) {
            return $matches[1];
        }

        return null;
    }

    function saveQueryResults(OutputInterface $output, String $job_id, int $total_records, int $start_offset, int $limit, String $path_to_save = null) {

        $return_arr=[];
        $is_polaris=false;

        /** ToDo: Implement check of file size and records to retrieve */
        if (empty($path_to_save)) {
            $today = date("Y-m-d-His");         // 2001-03-10-171618 
            $path_to_save = DEFAULT_RESULTS_DIR_PATH . "/sumologic_results-" . $today . ".json";
        }

        // Grab records in batches to ensure no memory exhaustion
        $max_limit = 5000;
        $record_count = 0;
        $offset = $start_offset;

        $fetch_limit = $limit;
        if ($fetch_limit > $max_limit) {
            $fetch_limit = $max_limit;
        }
        while($record_count <= $total_records) {
            $response = $this->apicontroller->getQueryResults($job_id,$offset,$fetch_limit);
            if(!file_put_contents($path_to_save, json_encode($response['body']->messages,JSON_PRETTY_PRINT), FILE_APPEND)) {
                return null;
            }

            if (sizeof(array_filter($response['body']->messages, function($value) {
                return $value->map->_collector === "Acquia Cloud Polaris";
            }))) {
                $is_polaris = 1;
            } else {
                $is_polaris = 0;
            }

            $upper = $record_count + (int) $fetch_limit;
            if($upper >= $total_records) {
                $upper = $total_records;
            }
            $output->writeln('Grabbed records ' . $record_count . ' to ' . $upper);
            $record_count += $fetch_limit;
            $offset += $fetch_limit;
            if($upper != $total_records) {
                $grab_count = $fetch_limit;
                if($upper + $fetch_limit > $total_records) {
                    $grab_count = $total_records - $upper;
                }
                $output->writeln('Grabbing ' . $grab_count . ' more ...');
            }
        }

        $return_arr['file_path'] = $path_to_save;
        $return_arr['is_polaris'] = $is_polaris;

        return $return_arr;
    }

    function isDateFormatCorrect($check_time) {

        $time_format = "Y-m-d\TH:i:s";
        $check_time_obj=DateTime::createFromFormat($time_format, $check_time);
        if(!$check_time_obj)
        {
            return FALSE;

        }
        return TRUE;

    }
}
