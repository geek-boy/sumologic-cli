<?php
// src/Command/ManageOrganizationsCommand.php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use DateTime;

use App\Controller\ApiController;
define("ISO_DATE_FORMAT", "Y-m-d\TH:i:s");
define("START_TIME_ARG", "start_time");
define("END_TIME_ARG", "end_time");
define("START_TIME_OPT", "start");
define("END_TIME_OPT", "end");

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
        ->addOption(
            START_TIME_OPT,
            null,
            InputOption::VALUE_REQUIRED,
            'Start time as relative. Examples are: "-3 hours" "-1 week" "-7 days" "2021-06-05T11:09:01"'
        )
        ->addOption(
            END_TIME_OPT,
            null,
            InputOption::VALUE_REQUIRED,
            'End time as relative time. Examples are: "-3 hours" "-1 week" "-7 days" "2021-06-05T11:09:01"'
        )
        ->addArgument('query_file_path', InputArgument::REQUIRED, 'The path to the file containing the Sumologic query you wish to run.')
        ->addArgument(START_TIME_ARG, InputArgument::OPTIONAL, '(Optional) The start time for the Query in ISO Date format. Example - 2010-01-28T15:00:00')
        ->addArgument(END_TIME_ARG, InputArgument::OPTIONAL, '(Optional) The end time for the Query in ISO Date format. Example - 2010-01-28T15:30:00')
      ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $start_time_opt = $input->getOption(START_TIME_OPT);

        $end_time_opt = $input->getOption(END_TIME_OPT);

        $start_time = $input->getArgument(START_TIME_ARG);

        $end_time = $input->getArgument(END_TIME_ARG);

        // Check Arguments and Options
        if ($start_time_opt === NULL && $start_time === NULL) {
            if($end_time_opt !== NULL) {
                $output->writeln("<error>Please provide a start time option ('--" . START_TIME_OPT . "') with the '--end' option.</error>");
                return Command::FAILURE;
            }
            $output->writeln("<error>Please provide a start time argument in ISO Date format or use the '--" . START_TIME_OPT . "' option.</error>");
            return Command::FAILURE;
        }

        if ($start_time_opt !== NULL && $start_time !== NULL) {
            $output->writeln("<error>Please select start time argument OR use the '--" . START_TIME_OPT . "' option.</error>");
            return Command::FAILURE;
        }

        if ($start_time === NULL) {
            $start_time = $start_time_opt;
        }

        if($end_time_opt !== NULL) {
            $end_time = $end_time_opt;
        }

        if(!($start_time_obj = $this->isDateFormatCorrect($start_time, $output)))
        {
            $output->writeln("<error>Incorrect format for start time, it should in ISO date or a relative time. Example - 2010-01-28T15:00:00</error>");
            return Command::FAILURE;
        }
        $start_time=$start_time_obj->format(ISO_DATE_FORMAT);
        if($start_time_obj->getTimestamp() > time()) {
            $output->writeln("<error>Start date and time needs to be before current time</error>");
            $output->writeln("<error>Start Time: " . $start_time_obj->format(ISO_DATE_FORMAT) . "</error>");
            return Command::FAILURE; 
         }

        if($end_time === NULL) {
            $end_time_obj = DateTime::createFromFormat(ISO_DATE_FORMAT, date(ISO_DATE_FORMAT));
            $end_time=$end_time_obj->format(ISO_DATE_FORMAT);
        } else {
            if(!($end_time_obj = $this->isDateFormatCorrect($end_time, $output)))
            {
                $output->writeln("<error>Incorrect format for end time, it should in ISO date. Example - 2010-01-28T15:00:00</error>");
                return Command::FAILURE;
            }     
            $end_time=$end_time_obj->format(ISO_DATE_FORMAT);

            if($end_time_obj->getTimestamp() > time()) {
                $output->writeln("<error>End date and time needs to be before current time</error>");
                $output->writeln("<error>End Time: " . $end_time_obj->format(ISO_DATE_FORMAT) . "</error>");
                return Command::FAILURE; 
             }
    
            if ($end_time_obj->getTimestamp() < $start_time_obj->getTimestamp()) {
                $output->writeln("<error>End date and time needs to be greater than the start date and time</error>");
                $output->writeln("<error>Start Time: " . $start_time_obj->format(ISO_DATE_FORMAT) . "</error>");
                $output->writeln("<error>End Time:   " . $end_time_obj->format(ISO_DATE_FORMAT) . "</error>");
                return Command::FAILURE; 
            }
        }

        $query_file = $input->getArgument('query_file_path');
        $fsObject = new Filesystem();
        if (!$fsObject->exists($query_file)) {
            $output->writeln("<error>Sorry - the query file does not exist!\nPlease provide the path to your query file.</error>");
            return Command::FAILURE;
        }
        
        $query = null;
        if(($query = file_get_contents($query_file)) === false) {
            $output->writeln("Unable to read query file :(");
            return Command::FAILURE;
        }

        // Check the query for Sumologic variable substitution denoted by '{{variable}}'
        preg_match_all('/{{.+}}/', $query, $matches);
        if($num_vars=count($matches[0])) {
            $output->writeln('<info>Found ' . $num_vars . ' variables in query :</info>');
            $prependBy = str_repeat(' ', 4);    // Add 4 spaces in front of our text
            foreach($matches[0] as $var) {
                $output->writeln('<info>' . $prependBy. '- ' . $var . '</info>');
            }
            $output->writeln("");
            $output_query = $query;
            $output_query = str_replace("\n","\n" . $prependBy,$output_query);
            $output->writeln('<info>' . $prependBy. 'Query:</info>');
            $output->writeln('<info>' . $prependBy .$output_query. '<info>');
            $output->writeln("");

            $helper = $this->getHelper('question');

            // Loop through each variable and get value
            //TODO: Is is possible to Validate value? 
            $var_values=[];
            foreach($matches[0] as $var) {
                $question = new Question('<question>Enter value for ' . $var . ': </question>');
                //TODO: Need to check for a NULL string
                $var_values[$var] = $helper->ask($input, $output, $question);
            }
            foreach($var_values as $var => $value) {
                $query = str_replace($var,$value,$query);
            }
        }
        
        $prependBy = str_repeat(' ', 4);    // Add 4 spaces in front of our text
        $output->writeln('<info>Making request to Sumologic Jobs for Query :</info>');
        $output->writeln('<info>' . $prependBy. 'Start Time :' . $start_time_obj->format(ISO_DATE_FORMAT) . '</info>');
        $output->writeln('<info>' . $prependBy. 'End Time   :' . $end_time_obj->format(ISO_DATE_FORMAT) . '</info>');
        $output->writeln("");
        $output_query = $query;
        $output_query = str_replace("\n","\n" . $prependBy,$output_query);
        $output->writeln('<info>' . $prependBy .$output_query. '<info>');
        $output->writeln("");

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
                    $output->writeln("<error>Oh Oh! Bad request - check the query format.</error>");
                    $output->writeln("<error>Status Code: " . $response['status_code'] . "</error>");
                    $output->writeln("<error>". $response['reason'] . "</error>");
                    return Command::FAILURE;
                    break;
                default:
                    $output->writeln("<error>Unknown response from Sumologic API - Exiting</error>");
                    $output->writeln("<error>Status Code: " . $response['status_code'] . "</error>");
                    $output->writeln("<error>" . $response['reason'] . "</error>");
                    return Command::FAILURE;
        }

        if($job_id === null) {
            $output->writeln("<error>Error retrieving Sumologic Job ID - Exiting</error>");
            return Command::FAILURE;
        }

        $output->writeln("Success! Query is running as JOB ID: " . $job_id);

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
                    $output->writeln("<error>Unknown response from Sumologic API - Exiting</error>");
                    $output->writeln('<error>Status code: ' .$response['status_code'] . '</error>');
                    $output->writeln('<error>' . $response['reason'] . '</error>');
                    return Command::FAILURE;
            }
            if($is_results_ready) {
                break;
            }
            sleep($delay);
        }
    
        if($result_count == -1) {
            $output->writeln("<error>Unknown failure for results - Exiting</error>");        
            return Command::FAILURE;
        } else if($result_count == 0) {
            $output->writeln("<info>Query returned no results :(</info>");            
            $output->writeln("<info>You may need to check your timeframes or your query.</info>");            
            return Command::SUCCESS;
        }

        $output->writeln('');
        $output->writeln("Query is ready!");
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('<question>There are ' . $result_count . ' records.' . ' Do you want to download these ?</question>', false);

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('');
            $output->writeln("<bg=white;fg=black>Ok! No results have been retrieved!</>");

            return Command::SUCCESS;
        }

        $output->writeln('');
        $output->writeln("<bg=yellow;options=bold>Grabbing results ...</>");
        $save_to_path = null;
        $result = $this->saveQueryResults($output,$job_id,$result_count,0,10000);
        $save_to_path = $result['file_path'];
        $is_kubernetes = $result['is_kubernetes'];
        if(empty($save_to_path)) {
            $output->writeln("<error>Error saving Results :(</error>");

            return Command::FAILURE;
        }
        $output->writeln('');
        $output->writeln("<fg=white;bg=blue;options=bold>Results saved to " . $save_to_path . "</>");
        $output->writeln("<info>Results are in json format.</info>");
        $output->writeln('');
        $output->writeln("<info>To view the results you could use the following shell command:</info>");
        if($is_kubernetes > 0) {
            $output->writeln("<comment>cat  " . $save_to_path . " | jq '.[].map | { \"timestamp\", \"namespace_name\", \"kubernetes.labels.app\",\"kubernetes.container_name\",\"log\"}' | less</comment>");
        } else {
            $output->writeln("<comment>cat  " . $save_to_path . " | jq '.[].map | { \"isodate\", \"namespace\", \"msg\"}' | less</comment>");
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
        $is_kubernetes=false;

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
                $is_kubernetes = 1;
            } else {
                $is_kubernetes = 0;
            }

            $upper = $record_count + (int) $fetch_limit;
            if($upper >= $total_records) {
                $upper = $total_records;
            }
            $output->writeln('<info>Grabbed records ' . $record_count . ' to ' . $upper . '</info>');
            $record_count += $fetch_limit;
            $offset += $fetch_limit;
            if($upper != $total_records) {
                $grab_count = $fetch_limit;
                if($upper + $fetch_limit > $total_records) {
                    $grab_count = $total_records - $upper;
                }
                $output->writeln('<info>Grabbing ' . $grab_count . ' more ...</info>');
            }
        }

        $return_arr['file_path'] = $path_to_save;
        $return_arr['is_kubernetes'] = $is_kubernetes;

        return $return_arr;
    }

    // Function to check time is in ISO date-time format. 
    // Returns DateTime object if success and FALSE in case of failure.
    function isDateFormatCorrect($check_time) {
        $timestamp = strtotime($check_time);
        if(!$timestamp) {
            return false;
        }

        $timestamp_str = date(ISO_DATE_FORMAT, $timestamp);
        $check_time_obj=DateTime::createFromFormat(ISO_DATE_FORMAT, $timestamp_str);
        if(!$check_time_obj)
        {
            return FALSE;

        }
        return $check_time_obj;

    }
}
