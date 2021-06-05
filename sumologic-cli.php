#!/usr/bin/env php
<?php
require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;

// Include App specific classes
use App\Controller\ApiController;
use App\Command\RequestQueryCommand;
// use App\Controller\ConfigController;

define("DEFAULT_CRED_FILE_PATH",getenv("HOME") . "/.sumologic-creds.yml");
define("SUMOLOGIC_JOB_SEARCH_API","https://api.sumologic.com/api/v1/search/jobs");
define("DEFAULT_RESULTS_DIR_PATH",getenv("HOME"));

$fsObject = new Filesystem();

// $config = new ConfigController();
// $config->getDefaultCredsPath();
// $config->getDefaultCredsPath();
// echo $config; 

// echo $default_creds_path;
// print_r($application);


if (!$fsObject->exists(DEFAULT_CRED_FILE_PATH))
{
    echo "Credentials file does not exist!\n";
    echo "The default file that is being looked for is : " . DEFAULT_CRED_FILE_PATH . "\n";
    echo "\nCreate this file with your Sumologic credentials.\n";
    echo "See https://service.sumologic.com/ui/#/preferences\n";
    echo "\nThe file format is :\n";
    echo "key : <your_sumologic_access_id>\n";
    echo "secret : <your_sumologic_access_password>\n";
    echo "\nAn example set up is :\n";
    echo "key : wdwqj2dqc4miqw\n";
    echo "secret : 2310didfwebc2368d234c23fc2fc234v4v23dwddqwehdbedededg\n";
    die(); 
}

$application = new Application("Sumologic CLI");

$apicontroller = new ApiController(DEFAULT_CRED_FILE_PATH,SUMOLOGIC_JOB_SEARCH_API);
// ... register commands
$application->add(new RequestQueryCommand($apicontroller));
// $application->add(new RequestQueryCommand());
// $application->add(new ManageOrganizationsCommand($apicontroller));
// $application->add(new EmployeeLiveDevCommand($apicontroller));

$application->run();
