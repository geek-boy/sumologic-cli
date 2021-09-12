<?php
namespace App\Controller;

include_once(__DIR__.'/../../config/constants.php');

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Yaml\Yaml;
use GuzzleHttp\Client as GuzzHttpClient;
// use GuzzleHttp\Cookie\CookieJarInterface;
// use GuzzleHttp\Psr7\Request as GuzzRequest;

class ApiController extends AbstractController /*extends SymfonyController*/
{
    private $httpclient;
    private $output;
    
    public function __construct(String $default_creds_path, String $sumologic_api_end_point)
    {

        $this->output = NULL;
        $this->downloadedBytesProgressBar = NULL;
        
        $creds = Yaml::parseFile($default_creds_path);

        $this->httpclient = new GuzzHttpClient(
            [
                'base_uri' => $sumologic_api_end_point,
                'cookies' => false,
                'headers' => [
                    'Content-type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'auth' => [$creds['key'], $creds['secret']],
                'http_errors' => false
            ]
         );
    }

    public function makeApiRequest(
        String $method, 
        String $api_end_point = '', 
        $data = null,
        bool $return_results_as_array = false,
        OutputInterface $output=null    // Output progress if not null
    ) {
        $path = (empty($api_end_point)) ? '' : '/' . $api_end_point; 
        $full_uri = SUMOLOGIC_JOB_SEARCH_API . $path;
        $this->output = $output;

        if(!empty($this->output)) {
            $this->downloadedBytesProgressBar = new ProgressBar($this->output, 0);
        }

        // Send the request.
        $response = null;
        $options = [];
        $downloadTotal = 0;
        $downloadedBytes = 0;
        $uploadTotal = 0;
        $uploadedBytes = 0;

        if($data !== null) {
            $options = ['body' => $data];
            $response = $this->httpclient->request($method,$full_uri,$options);
        } else {
            if (!empty($this->output)) {   
                $this->downloadedBytesProgressBar->setFormat('api_controller_downloaded_bytes');
                $this->downloadedBytesProgressBar->start();
                $this->downloadedBytesProgressBar->setMessage("full_uri: " . $full_uri);
                $this->downloadedBytesProgressBar->display();          
            }
            
            $response = $this->httpclient->request($method,$full_uri,[
                'headers' => ['Accept: application/json'],
                'progress' => function(
                    $downloadTotal,
                    $downloadedBytes,
                    $uploadTotal,
                    $uploadedBytes
                ) {
                    if (!empty($this->output)) {
                        $timezone = date_default_timezone_get();
                        $date = date('Y-m-d h:i:s a', time());
                        $this->downloadedBytesProgressBar->clear();
                        $this->downloadedBytesProgressBar->setMessage($date. ' ' . $timezone, 'date');
                        $this->downloadedBytesProgressBar->setMessage($downloadedBytes, 'downloadedBytes');
                        $this->downloadedBytesProgressBar->advance();
                        $this->downloadedBytesProgressBar->display();          
                    }
                },
            ]);
            if (!empty($this->output)) {

                $this->downloadedBytesProgressBar->finish();
            }
        }

        return ['status_code' => $response->getStatusCode(),
                'body' => json_decode($response->getBody(),$return_results_as_array),
                'reason' => $response->getReasonPhrase()
        ];
    }

    public function createSearchJob(String $query) {
        return $this->makeApiRequest('POST','',$query);
    }

    public function getSearchJobStatus(String $jobid) {
        return $this->makeApiRequest('GET',$jobid);
    }


    /**
     * @param String $jobid // Job ID for the query
     * @param int $offset   // Return records starting at this offset    
     * @param int $limit    // The number of records starting at offset to return 
     * @param bool $return_messages // Return results as 'messages' or as 'records'
     * @param bool $return_results_as_array // Return results as an array rather than as an object
     * @param OutputInterface $output=null  // OutputInterface instance to print out to console
     * 
     * @return type
     */
    public function getJobQueryResults(
        String $jobid,
        int $offset,
        int $limit,
        bool $return_messages=true,
        bool $return_results_as_array=false,
        OutputInterface $output=null
    ) {
        $ret=null;
        if (!$return_messages) {
            $ret = $this->makeApiRequest('GET',$jobid . '/records?offset=' . $offset . '&limit='.$limit,null,$return_results_as_array,$output);

        } else {
            $ret = $this->makeApiRequest('GET',$jobid . '/messages?offset=' . $offset . '&limit='.$limit,null,$return_results_as_array,$output);
        }
        
        return $ret;

    } 
}
