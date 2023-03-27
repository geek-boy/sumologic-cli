<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use DateTime;
use App\Controller\ApiController;

define("ISO_DATE_FORMAT", "Y-m-d\TH:i:s");
define("QUERY_FILE_PATH_ARG", "query_file_path");
define("START_TIME_ARG", "start_time");
define("END_TIME_ARG", "end_time");
define("START_TIME_OPT", "start");
define("END_TIME_OPT", "end");
define("QUERY_OPT", "search-query");
define("RESULTS_MESSAGES_OPT", "messages");
define("RESULTS_AGGREGATE_RECORDS_OPT", "aggregate-records");
define("RESULTS_FIELDS_OPT", "fields-only");
define("RESULTS_DOWNLOAD", "--download");
define("RESULTS_CONTINUE_DOWNLOAD", "--download-do-not-confirm-size");
define("FORMAT_OPT", "format");
define('FORMAT_OPTIONS', array(
    'json' => array('ext' => 'json', 'delimiter' => ''),
    'csv' => array('ext' => 'csv', 'delimiter' => ","),
    'tab'=> array('ext' => 'tab' , 'delimiter' => "\t")
));

/**
 * QueryRunCommand
 * 
 * App\Command\QueryRunCommand
 */
class QueryRunCommand extends Command {

  /**
   * @var String
   *  @todo unknown.
   */
  protected static $defaultName = 'query:run';

  /**
   * @var String
   *  @todo unknown.
   */
  protected static $defaultDescription = 'Run a Query and save results locally in a file. Use the \'--help\' option to see more details.';

  /**
   * @var App\Controller\ApiController
   *  @todo unknown.
   */
  protected $apicontroller = null;

  /**
   * 
   * @param App\Controller\ApiController $apicontroller
   */
  public function __construct(ApiController $apicontroller) {
    $this->apicontroller = $apicontroller;
    parent::__construct();
  }

  /**
   * 
   * @return void
   */
  protected function configure(): void {
    $this
            ->setDescription(self::$defaultDescription)
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command makes a request to the Sumologic Job Search API to run a Query and save results locally.' . PHP_EOL .
              PHP_EOL .
              'Examples ways to run the command:' . PHP_EOL .
              '  * ' . APP_COMMAND_NAME . ' ' . self::$defaultName . ' /home/user/query_file 2021-06-05T11:09:00 2021-06-05T12:09:00' . PHP_EOL .
              '  * ' . APP_COMMAND_NAME . ' ' . self::$defaultName . ' --' . END_TIME_OPT . '="-7days" /home/user/query_file.txt 2021-06-05T11:09:00' . PHP_EOL .
              '  * ' . APP_COMMAND_NAME . ' ' . self::$defaultName . ' --' .FORMAT_OPT. '=csv /home/user/query_file 2021-06-05T11:09:00 2021-06-05T12:09:00' . PHP_EOL .
              '  * ' . APP_COMMAND_NAME . ' ' . self::$defaultName . ' --' . QUERY_OPT . '="namespace=agoorah.apache-access" 2021-06-05T11:09:00 2021-06-05T12:09:00' . PHP_EOL .
              '  * ' . APP_COMMAND_NAME . ' ' . self::$defaultName . ' --' . QUERY_OPT . '="namespace=agoorah.apache-access" --' . START_TIME_OPT . '="-2hours" --' . END_TIME_OPT . '="-1hour"' . PHP_EOL .
              '  * ' . APP_COMMAND_NAME . ' ' . self::$defaultName . ' --' . QUERY_OPT . '="namespace=agoorah.apache-access" --' . START_TIME_OPT . '="-2hours" --' . END_TIME_OPT . '="-1hour" --' . FORMAT_OPT. '=tab' . PHP_EOL .
              '  * ' . APP_COMMAND_NAME . ' ' . self::$defaultName . ' --' . QUERY_OPT . '="namespace=agoorah.apache-access" --' . START_TIME_OPT . '="-2hours"' . PHP_EOL . 
              '  * ' . APP_COMMAND_NAME . ' ' . self::$defaultName . ' --' . QUERY_OPT . '="namespace=agoorah.apache-access" --'. RESULTS_FIELDS_OPT . ' --' . START_TIME_OPT . '="-2hours" --' . END_TIME_OPT . '="-1hour"' . PHP_EOL .
              '  * ' . APP_COMMAND_NAME . ' ' . self::$defaultName . ' --' . QUERY_OPT . '="namespace=agoorah.apache-access" --'. RESULTS_FIELDS_OPT . ' --' . START_TIME_OPT . '="-2hours" --' . END_TIME_OPT . '="-1hour" --' . FORMAT_OPT. '=tab' . PHP_EOL .
              PHP_EOL .
              'See https://www.php.net/manual/en/class.datetimeinterface.php for ISO Date format.' . PHP_EOL .
              'See https://www.php.net/manual/en/datetime.formats.relative.php for valid relative time formats.'
            )

            // Define Options
            ->addOption(
                    FORMAT_OPT,
                    null,
                    InputOption::VALUE_REQUIRED,
                    'Format of downloaded results. This can be "json", "csv", "tab". The default format is "json".'
            )
            ->addOption(
                    FORMAT_OPTIONS['json']['ext'],
                    null,
                    InputOption::VALUE_NONE,
                    'Download results in "' . FORMAT_OPTIONS['json']['ext'] . '" format.'
            )
            ->addOption(
                    FORMAT_OPTIONS['csv']['ext'],
                    null,
                    InputOption::VALUE_NONE,
                    'Download results in "' . FORMAT_OPTIONS['csv']['ext'] . '" format.'
            )
            ->addOption(
                    FORMAT_OPTIONS['tab']['ext'],
                    null,
                    InputOption::VALUE_NONE,
                    'Download results in "' . FORMAT_OPTIONS['tab']['ext'] . '" format.'
            )
            ->addOption(
                    QUERY_OPT,
                    null,
                    InputOption::VALUE_REQUIRED,
                    'Provide a simple Sumologic search query to run. For example \'--' . QUERY_OPT . '="namespace=agoorah.apache-access"\''
            )
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
            ->addOption(
                RESULTS_MESSAGES_OPT,
                null,
                InputOption::VALUE_NONE,
                'Retrieve the results as messages'
            )
            ->addOption(
                RESULTS_AGGREGATE_RECORDS_OPT,
                null,
                InputOption::VALUE_NONE,
                'Retrieve the results as aggregated records'
            )
            ->addOption(
                RESULTS_FIELDS_OPT,
                null,
                InputOption::VALUE_NONE,
                'Print out only list of fields for query - Optional'
            )
            ->addOption(
              RESULTS_DOWNLOAD,
              null,
              InputOption::VALUE_NONE,
              'Download the results without confirmation'
          )
          ->addOption(
            RESULTS_CONTINUE_DOWNLOAD,
            null,
            InputOption::VALUE_NONE,
            'Do not check file size during download'
        )
        ->addArgument(QUERY_FILE_PATH_ARG, InputArgument::OPTIONAL, "(Optional) The path to the file containing the Sumologic search query you wish to run. If you do not use this option then you must provide the '" . QUERY_OPT . "' option.")
        ->addArgument(START_TIME_ARG, InputArgument::OPTIONAL, '(Optional) The start time for the Query in ISO Date format. Example - 2010-01-28T15:00:00')
        ->addArgument(END_TIME_ARG, InputArgument::OPTIONAL, '(Optional) The end time for the Query in ISO Date format. Example - 2010-01-28T15:30:00')
    ;
  }

  /**
   * 
   * @param InputInterface $input
   *   @todo unknown.
   * @param OutputInterface $output
   *   @todo unknown.
   * @return array
   *   @todo unknown.
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $output_format = 'json';

    $query_file = $input->getArgument(QUERY_FILE_PATH_ARG);

    $start_time = $input->getArgument(START_TIME_ARG);

    $end_time = $input->getArgument(END_TIME_ARG);

    $start_time_opt = $input->getOption(START_TIME_OPT);

    $end_time_opt = $input->getOption(END_TIME_OPT);

    $format_option = $input->getOption(FORMAT_OPT);

    $user_confirm_size_download = empty(RESULTS_CONTINUE_DOWNLOAD);

    // Check Arguments and Options 
    // Note that as all arguments are optional then we  need to check that 
    // the value for an argument maybe assigned to a different argument.
    // Check if we have no queries being requested or 
    $query = $input->getOption(QUERY_OPT);
    if ((empty($query) && empty($query_file))) {
      $output->writeln("<error>Please provide the path to your search query file ('" . QUERY_FILE_PATH_ARG . "') OR use the '" . QUERY_OPT . "' option." . PHP_EOL .
              "Use the '--help' option to see more details.</error>");
      return Command::FAILURE;
    }

    // if the QUERY_OPT has not been given then check other arguments
    if (empty($query)) {
      if (empty($start_time_opt) && empty($end_time_opt)) {
        // we expect three arguments to be given
        if (empty($query_file) || empty($start_time) || empty($end_time)) {
          $output->writeln("<error>Please provide all arguments OR use the '" . START_TIME_OPT . "' and '" . END_TIME_OPT . "' options." . PHP_EOL .
                  "Use the '--help' option to see more details and examples.</error>");
          return Command::FAILURE;
        }
      }
    }


    // if we have been given a QUERY_OPT then we need to check if 
    // the other arguments are times or a query file
    if (!empty($query)) {
      $end_time = $start_time;
      $start_time = $query_file;
      $query_file = null;
    }

    /** Define the type of results to return
     *  - pnly messages
     *  - only aggregated records 
     *  - only fields
     *  - all messages and aggregated records
     * */
    $results_return_list = [];  //  Empty array means return all result types
    
    if ($input->getOption(RESULTS_MESSAGES_OPT)) {
        array_push($results_return_list,'messages');
    }
    if ($input->getOption(RESULTS_AGGREGATE_RECORDS_OPT)) {
        array_push($results_return_list,'records');
    }
    if ($input->getOption(RESULTS_FIELDS_OPT)) {
        array_push($results_return_list,'fields');
    }

    // Check the requested output format options
    $format_options = [];
    $opt_count = 0;
    foreach (FORMAT_OPTIONS as $option_type => $option_vals) {
      $format_options[$option_type] = $input->getOption($option_type);
      if ($format_options[$option_type] != null) {
        $output_format = $option_type;
        $opt_count++;
      }
      if ($opt_count > 1) {
        $output->writeln("<error>Please provide only one format option.</error>");
        return Command::FAILURE;
      }
    }

    if ($opt_count == 1 && $format_option != null) {
      $output->writeln("<error>Please provide only one format option.</error>");
      return Command::FAILURE;
    } else if ($format_option != null) {
      $output_format = $format_option;
    }


    // Check the time options
    if ($start_time_opt === NULL && $start_time === NULL) {
      if ($end_time_opt !== NULL) {
        $output->writeln("<error>Please provide a start time option ('--" . START_TIME_OPT . "') with the '--end' option or provide a start time in ISO Date format.</error>");
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

    if ($end_time_opt !== NULL) {
      $end_time = $end_time_opt;
    }

    if (!($start_time_obj = $this->isDateFormatCorrect($start_time, $output))) {
      $output->writeln("<error>Incorrect format for start time, it should in ISO date or a relative time. Example - 2010-01-28T15:00:00</error>");
      return Command::FAILURE;
    }
    $start_time = $start_time_obj->format(ISO_DATE_FORMAT);
    if ($start_time_obj->getTimestamp() > time()) {
      $output->writeln("<error>Start date and time needs to be before current time</error>");
      $output->writeln("<error>Start Time: " . $start_time_obj->format(ISO_DATE_FORMAT) . "</error>");
      return Command::FAILURE;
    }

    if ($end_time === NULL) {
      $end_time_obj = DateTime::createFromFormat(ISO_DATE_FORMAT, date(ISO_DATE_FORMAT));
      $end_time = $end_time_obj->format(ISO_DATE_FORMAT);

      $helper = $this->getHelper('question');
      $question = new ConfirmationQuestion('<question>No end time has been given.' . PHP_EOL .
              'Do you want to use the time now (' . $end_time . ') ?</question>', false);


      if (!$helper->ask($input, $output, $question)) {
        $output->writeln('');
        $output->writeln("<bg=white;fg=black>Ok! No query has been run!</>");

        return Command::SUCCESS;
      }
    } else {
      if (!($end_time_obj = $this->isDateFormatCorrect($end_time, $output))) {
        $output->writeln("<error>Incorrect format for end time, it should in ISO date. Example - 2010-01-28T15:00:00</error>");
        return Command::FAILURE;
      }
      $end_time = $end_time_obj->format(ISO_DATE_FORMAT);

      if ($end_time_obj->getTimestamp() > time()) {
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

    //Check if we are using the $query_file to get the query
    if (!empty($query_file)) {
      if (!is_readable($query_file)) {
        $output->writeln("<error>Sorry - The path to the search query file was not found or I cannot read it!\n" .
                "Please check the path to your query file or use the '" . QUERY_OPT . "' option.</error>");
        return Command::FAILURE;
      }

      $fsObject = new Filesystem();
      if (($query = file_get_contents($query_file)) === false) {
        $output->writeln("<error>Oh dear! I am unable to read query file :(</error>");
        return Command::FAILURE;
      }
    }

    /** Check the query for Sumologic variable substitution denoted by '{{variable}}' */
    preg_match_all('/{{.+}}/', $query, $matches);
    if ($num_vars = count($matches[0])) {
      $output->writeln('<info>Found ' . $num_vars . ' variables in query :</info>');
      $prependBy = str_repeat(' ', 4);    // Add 4 spaces in front of our text
      foreach ($matches[0] as $var) {
        $output->writeln('<info>' . $prependBy . '- ' . $var . '</info>');
      }
      $output->writeln("");
      $output_query = str_replace("\n", "\n" . $prependBy, $query);
      $output->writeln('<info>' . $prependBy . 'Query:</info>');
      $output->writeln('<info>' . $prependBy . $output_query . '<info>');
      $output->writeln("");

      $helper = $this->getHelper('question');

      // Loop through each variable and get value
      //TODO: Is is possible to Validate value? 
      $var_values = [];
      foreach ($matches[0] as $var) {
        $question = new Question('<question>Enter value for ' . $var . ': </question>');
        //TODO: Need to check for a NULL string
        $var_values[$var] = $helper->ask($input, $output, $question);
      }
      foreach ($var_values as $var => $value) {
        $query = str_replace($var, $value, $query);
      }
    }

    // OK we now can make an API request to get results
    $prependBy = str_repeat(' ', 4);    // Add 4 spaces in front of our text
    $output->writeln('<info>Making request to Sumologic Jobs for Query :</info>');
    $output->writeln('<info>' . $prependBy . 'Start Time :' . $start_time_obj->format(ISO_DATE_FORMAT) . '</info>');
    $output->writeln('<info>' . $prependBy . 'End Time   :' . $end_time_obj->format(ISO_DATE_FORMAT) . '</info>');
    $output->writeln("");
    $output_query = str_replace("\n", "\n" . $prependBy, $query);
    $output->writeln('<info>' . $prependBy . $output_query . '<info>');
    $output->writeln("");

    // Clean up formatting so as to pass this properly to API end
    $query = str_replace('"', '\"', $query);
    $query = str_replace("\n", '', $query);

    // @todo validate $query
    $json_query = '{"query" :"' . $query . '",' .
            '"from": "' . $start_time . '",' .
            '"to": "' . $end_time . '",' .
            '"timeZone": "UTC",' .
            '"byReceiptTime": false' .
            '}';

    $response = $this->apicontroller->createSearchJob($json_query);

    // Status codes defined by sumologic API.
    // https://help.sumologic.com/APIs/Search-Job-API/About-the-Search-Job-API#errors

    $job_id = null;
    switch ($response['status_code']) {
      case 202:
        $job_id = $this->getQueryJobID($response['body']->link->href);
        break;

      case 301:
        $output->writeln("The requested resource SHOULD be accessed through returned URI in Location Header.");
        return Command::FAILURE;

      case 400:
        $output->writeln("<error>Oh Oh! Whoops sumologic did not like the query. Looks like you query syntax is incorrect.</error>");
        $output->writeln("<error>Check the query format !</error>");
        $output->writeln("<error>Status Code: " . $response['status_code'] . "</error>");
        $output->writeln("<error>" . $response['reason'] . "</error>");
        return Command::FAILURE;

      case 401:
        $output->writeln("<error>Sumologic Credentials could not be verified. Please check your key and secret in " . DEFAULT_CRED_FILE_PATH . "</error>");
        return Command::FAILURE;

      case 403:
        $output->writeln("<error>This operation is not allowed for your Sumologic account type.</error>");
        return Command::FAILURE;

      case 404:
        $output->writeln("<error>Requested resource could not be found.</error>");
        return Command::FAILURE;

      case 405:
        $output->writeln("Unsupported method for URL.");
        return Command::FAILURE;

      case 415:
        $output->writeln("Invalid content type.");
        return Command::FAILURE;

      case 429:
        $output->writeln("The API request rate is higher than 4 request per second or your organization has exceeded the 200 active concurrent search job limit.");
        return Command::FAILURE;

      case 500:
        $output->writeln("Internal server error.");
        return Command::FAILURE;

      case 503:
        $output->writeln("Service is currently unavailable.");
        return Command::FAILURE;

      default:
        $output->writeln("<error>Unknown response from Sumologic API - Exiting</error>");
        $output->writeln("<error>Status Code: " . $response['status_code'] . "</error>");
        $output->writeln("<error>" . $response['reason'] . "</error>");
        return Command::FAILURE;
    }

    if ($job_id === null) {
      $output->writeln("<error>Error retrieving Sumologic Job ID - Exiting</error>");
      return Command::FAILURE;
    }

    $output->writeln("Success! Query is running as JOB ID: " . $job_id);

    // We have a job running - now wait until results are gathered 
    $is_results_ready = false;
    $delay = 2; // Amount of seconds before making a new request
    $result_count = -1; // Initialise to -1 as '0' is a valid result
    while (!$is_results_ready) {
      // progress bar here
      $response = $this->apicontroller->getSearchJobStatus($job_id);

      switch ($response['status_code']) {
        case 200:
          if ($response['body']->state == "DONE GATHERING RESULTS") {
            $is_results_ready = true;
          }
          break;
        default:
          $output->writeln("<error>Unknown response from Sumologic API - Exiting</error>");
          $output->writeln('<error>Status code: ' . $response['status_code'] . '</error>');
          $output->writeln('<error>' . $response['reason'] . '</error>');
          return Command::FAILURE;
      }
      if ($is_results_ready) {
        break;
      }
      sleep($delay);
    }

    $job_status = $response['body'];
    if(property_exists($job_status,"histogramBuckets")) {
        unset($job_status->histogramBuckets);
    }
    
    if($job_status->messageCount == 0) {
        $output->writeln("<info>Query returned no results :(</info>");            
        $output->writeln("<info>You may need to check your timeframes or your query.</info>");            
        return Command::SUCCESS;
    }

    if(in_array('records',$results_return_list)) {
        if($job_status->recordCount == 0) {
            $output->writeln("<info>Query has no aggregate results :(</info>");            
            $output->writeln("<info>You may need to check your query.</info>");            
            return Command::SUCCESS;
        }
    }


    $output->writeln('');
    $output->writeln("Query is ready!");
    if(!empty($input->getOption(FORMAT_OPT))) {
      $helper = $this->getHelper('question');
      //Add the calculation for messages here. 
      //$estimated_log_file_bytes = ($response['body']->messageCount) * 500;

      // Ask user if they want to download messages
      $confirm_str = 'There are ' . $job_status->messageCount . ' messages.';
      if(in_array('records',$results_return_list)) {
          $confirm_str = 'There are ' . $job_status->recordCount . ' aggregate records.';
      }
      $question = new ConfirmationQuestion('<question>' . $confirm_str . ' Do you want to download this log file ?</question>', false);

      if (!$helper->ask($input, $output, $question)) {
          $output->writeln('');
          $output->writeln("<bg=white;fg=black>Ok! No results have been retrieved!</>");

          return Command::SUCCESS;
      }
    }

    /** User wants to download results - let's get them and save them locally. */
    $output->writeln('');
    $output->writeln("<bg=black;fg=magenta;options=bold>Grabbing results ...</>");
    $save_to_path = null;
    $result=$this->saveQueryResults($input,$output,$job_id,$job_status,$results_return_list,$output_format,$user_confirm_size_download);

    if(empty($result)) {
        $output->writeln("<error>Error saving Results :(</error>");

        return Command::FAILURE;
    }

    /** Results have been saved - Let the user know! */
    $save_to_path = $result['file_path'];
    $is_kubernetes = $result['is_kubernetes'];

    $output->writeln('');
    $output->writeln("<fg=white;bg=blue;options=bold>Results saved to " . $save_to_path . "</>");
    $output->writeln("<info>Results are in " . $output_format . " format.</info>");
    $output->writeln('');
    $output->writeln("<info>To view the results you could use the following shell command:</info>");
    $outputStyle = new OutputFormatterStyle('magenta', 'black', ['bold', 'blink']);
    $output->getFormatter()->setStyle('fire', $outputStyle);
    if($output_format == FORMAT_OPTIONS['json']['ext']) {
      if($is_kubernetes > 0) {
          $output->writeln("<fire>cat  " . $save_to_path . " | jq '.[].map | { \"timestamp\", \"namespace_name\", \"kubernetes.labels.app\",\"kubernetes.container_name\",\"log\"}' | less</fire>");
      } else {
          if(in_array('messages',$results_return_list)) {
              $out_str = "cat  " . $save_to_path . " |  jq '.[] | {\"isodate\",\"_raw\"}' | less";
          } else if(in_array('records',$results_return_list)) {
              $out_str = "cat  " . $save_to_path . " | jq '.[]' | less";
          } else {
              $out_str = "cat  " . $save_to_path . " | jq '.[] | { \"isodate\", \"namespace\", \"msg\"}' | less";
          }
          $output->writeln("<fire>$out_str</fire>");
      }
    } else {
        $output->writeln("<fire>cat  " . $save_to_path . " | less</fire>");
    }
    $output->writeln('');

    return Command::SUCCESS;
  }


  /**
   * Query results can be requested to return either
   * - only messages
   * - only messages fields
   * - both messages and fields
   * - only records
   * - only records fields
   * - both records and fields
   * @param InputInterface $input
   * @param ConsoleOutputInterface $output
   * @param String $job_id,
   * @param array $results_count, // $results_count['messages] = xxx
   *                              // $results_count['messages][fields] = true 
   *                              // $results_count['records] = xxx 
   *                              // $results_count['records][fields] = true 
   * @param int $start_offset, 
   * @param int $limit, 
   * @param String $file_format = 'json',
   * @param bool $user_confirm_continue_download = true,
   * @param String $return_list = 'messages',   // Valid Values 
   *                                           // 'messages', 'messages-fields', 'messages-all'
   *                                           // 'records', 'records-fields', 'records-all'
   * @param String $path_to_save = null
   * 
   * 
   * @return int
  */
  public function saveQueryResults(
    $input,
    $output, 
    $job_id,
    $job_status, 
    $results_return_list = [],
    $file_format = 'json',
    $user_confirm_continue_download = true,
    $start_offset=0, 
    $limit=5000, 
    $path_to_save = null
  ) 
{
    $return_only_fields = false;

    //TODO: handle delimiter output for when $return_list='all'
    if (($key = array_search('fields', $results_return_list)) !== false) {
        unset($results_return_list[$key]);
        $return_only_fields=true;
        $limit = 1;
    }

    if (empty($results_return_list)) {
        $results_return_list = ['records','messages'];
        $results_return_list = ['messages','records'];
    }

    $return_arr=[];
    $is_kubernetes=false;
    $file_size_increment = 200;
    $log_size_upper_limit = $file_size_increment*1024*1024; // defined in bytes

    // @todo Implement check of file size and records to retrieve 
    if (empty($path_to_save)) {
      $return_type_str = null;

      if($return_only_fields) {
          $return_type_str = 'fields-';
      }
      if(count($results_return_list) === 1) {
           $return_type_str = $results_return_list[0] . '-';
          if($return_only_fields) {
              $return_type_str = $results_return_list[0] . '-fields-';
          }
      }

      $today = date("Y-m-d-His"); // eg 2001-03-10-171618 
      $path_to_save = DEFAULT_RESULTS_DIR_PATH . "/sumologic_results-" . $return_type_str . $today . "." . $file_format;
    }

    // Grab records in batches to ensure no memory exhaustion
    $max_limit = 5000;
    $record_count = 0;
    $offset = $start_offset;

    $fetch_limit = $limit;
    if ($fetch_limit > $max_limit) {
      $fetch_limit = $max_limit;
    }

    // Open file to save results
    $fp = fopen($path_to_save, 'w');
    if (!$fp) {
      return null;
    }  

    $section1 = $output->section();
    $section2 = $output->section();

    $progressBar1 = new ProgressBar($section1);
    $progressBar1->setFormat('request_query_record_progress');
    $progressBar2 = new ProgressBar($section2);

    foreach($results_return_list as $result_type) {
        $download_result_count = 0;

        $results_count = ($result_type == 'messages') ? $job_status->messageCount : $job_status->recordCount; 
        $is_messages = ($result_type == 'messages');

        $progressBar1->setMessage(PHP_EOL . "Starting to gather $result_type results" . PHP_EOL);
        $progressBar1->start($results_count);

        // If $return_list is 'fields' then we only need to grab the first set of these
        while($results_count > 0 && $download_result_count <= $results_count) {
            $upper = $download_result_count + (int) $fetch_limit;
            if($upper >= $results_count) {
                $upper = $results_count;
            }
            $progressBar1->clear();
            $progressBar1->setMessage($result_type, 'recordType');
            $progressBar1->setMessage($download_result_count, 'recordCount');
            $progressBar1->setMessage($upper, 'upperLimit');
            $progressBar1->advance($fetch_limit);
            $progressBar1->display();
            
            $return_results_as_array = ($file_format != 'json');
            $response = $this->apicontroller->getJobQueryResults($job_id,$offset,$fetch_limit,$is_messages,$return_results_as_array,$output);
            
            if($file_format == 'json') {
                $results=$response['body'];

                if($return_only_fields) {
                    $results = $response['body']->fields;
                } else {
                  if(property_exists($response['body'], $result_type)) { 
                    $results = $response['body']->$result_type;
                    foreach ($results as $key => $item) {
                        $results[$key] = $item->map;
                    }
                  }
                }
                if(property_exists($response['body'], $result_type)) { 
                  if(!file_put_contents($path_to_save, json_encode($results,JSON_PRETTY_PRINT), FILE_APPEND)) {
                      return null;
                  }
                }
            } else {
                /** TODO Fix up using $return_only_fields for arrays */
                // We get results back as an associative array
                $response_arr=$response['body'];

                if($return_only_fields) {
                    $fields = [];
                    $fields['fields'] = $response['body']['fields'];
                    $response_arr=$fields['fields'];
                } else {
                    $response_arr=$response['body'][$result_type];
                }

                // Add a heading before the results
                if(!fwrite($fp,'******* ' .$result_type . ' *******' . PHP_EOL)) {
                    return null;
                }

                // Loop through each result item and add to file
                foreach($response_arr as $key => $result_item) {
                    $item=$result_item;
                    if (!$return_only_fields) {
                        $item = $result_item['map'];
                    }

                    if(!fputcsv($fp, $item,FORMAT_OPTIONS[$file_format]['delimiter'])) {
                        return null;
                    }
                }
            }

            if($result_type == 'messages') {
                if(!$return_results_as_array) {
                  if(property_exists($response['body'], $result_type)) {
                    // We have an object
                    if (sizeof(array_filter($response['body']->$result_type, function($value) {
                        return $value->map->_collector === "Acquia Cloud Polaris";
                    }))) {
                        $is_kubernetes = 1;
                    } else {
                        $is_kubernetes = 0;
                    }
                  }
                } else {
                    // We have an array
                    if (sizeof(array_filter($response['body'][$result_type], function($value) {
                        return $value['map']['_collector'] === "Acquia Cloud Polaris";
                    }))) {
                        $is_kubernetes = 1;
                    } else {
                        $is_kubernetes = 0;
                    }  
                }
            }

            $download_result_count += $fetch_limit;
            $offset += $fetch_limit;
            if($upper != $results_count) {
                $grab_count = $fetch_limit;
                if($upper + $fetch_limit > $results_count) {
                    $grab_count = $results_count - $upper;
                }
            }
            $return_arr['file_path'] = $path_to_save;
            $return_arr['is_kubernetes'] = $is_kubernetes;

            // Calculating the log file size. 
            $log_file_size = filesize($path_to_save);
            $log_file_size = $this->calculate_log_file_size($log_file_size);

            $progressBar2->setFormat('request_query_file_size_progress');
            $progressBar2->clear();
            $progressBar2->setMessage($log_file_size, 'logFileSize');
            $progressBar2->display();

            if($user_confirm_continue_download) {
              $helper = $this->getHelper('question');
              if ($log_file_size >= $log_size_upper_limit) {
                  $question = new ConfirmationQuestion('Current log file size is ' . $log_file_size . ' Continue to retrieve more results?', false);
                  $log_size_upper_limit = $log_size_upper_limit + ($file_size_increment*  24*1024); // multiples of bytes

                  if (!$helper->ask($input, $output, $question)) {
                      $progressBar1->clear();
                      $progressBar1->finish();
                      $progressBar1->display();
                      $progressBar2->finish();
                      return $return_arr;
                  }
              }
            }

            // If we are only getting 'fields' then our job here is done.
            if ($return_only_fields) {
                break;
            }
        }
    }

    fclose($fp);

    $progressBar1->clear();
    $progressBar1->finish();
    $progressBar1->display();
    $progressBar2->finish();
    return $return_arr;
  }
  
  /**
   * 
   * @param string $href
   * @return int
   */
  public function getQueryJobID($href) {
        $pattern = str_replace("/","\/",SUMOLOGIC_JOB_SEARCH_API);
        $pattern = '/' . $pattern . '\/'. '([0-9A-Za-z]+)'.'/';
        preg_match($pattern, $href, $matches);

        if(count($matches) == 2) {
            return $matches[1];
        }


      return Command::FAILURE;
    }
  
  /**
   * 
   * @param String $check_time
   * @return mixed
   */
   public function isDateFormatCorrect($check_time) {
    $timestamp = strtotime($check_time);
    if (!$timestamp) {
      return false;
    }

    $timestamp_str = date(ISO_DATE_FORMAT, $timestamp);
    $check_time_obj = DateTime::createFromFormat(ISO_DATE_FORMAT, $timestamp_str);
    if (!$check_time_obj) {
      return false;
    }
    return $check_time_obj;
  }

  /**
   * Calculates log file size and returns the file size in KB, MB or GB.
   * 
   * @param int $bytes
   * @return string
   */
  public function calculate_log_file_size($bytes) {

    if ($bytes >= 1073741824) {
      $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
      $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
      $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
      $bytes = $bytes . ' bytes';
    } elseif ($bytes == 1) {
      $bytes = $bytes . ' byte';
    } else {
      $bytes = '0 bytes';
    }

    return $bytes;
  }
}
