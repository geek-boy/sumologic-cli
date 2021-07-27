<?php
namespace App\Controller;

include_once(__DIR__.'/../../config/constants.php');

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use GuzzleHttp\Client as GuzzHttpClient;
use GuzzleHttp\Cookie\CookieJarInterface;
use GuzzleHttp\Psr7\Request as GuzzRequest;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Api controller class ...
 * 
 * extends SymfonyController
 */
class ApiController extends AbstractController 
{
    /**
     * 
     * @var type
     */
    private $httpclient;
    
    /**
     * 
     * @var type
     */
    private $jar;
    
    /**
     * 
     * @var type
     */
    private $output;
    
    /**
     * @todo not used in scope
     * @var type
     */
    private $downloadedBytesProgress;
     
   /**
    * 
    * @param type $default_creds_path
    * @param type $sumologic_api_end_point
    */
    public function __construct($default_creds_path, $sumologic_api_end_point)
    {

        $this->output = NULL;
        $this->downloadedBytesProgressBar = NULL;
        
        $creds = Yaml::parseFile($default_creds_path);

        $this->httpclient = new GuzzHttpClient(
            [
                'base_uri' => $sumologic_api_end_point,
                'cookies' => true,
                'headers' => [
                    'Content-type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'auth' => [$creds['key'], $creds['secret']],
                'http_errors' => false
            ]
         );
        $this->jar = new \GuzzleHttp\Cookie\CookieJar;
    }

    /**
     * 
     * @param String $method
     * @param String $api_end_point
     * @param type $data
     * @param OutputInterface $output
     * @return type
     */
    public function makeApiRequest(
        String $method, 
        String $api_end_point = '', 
        $data = null,
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
        
        //@todo not used at this point in time
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
                'body' => json_decode($response->getBody()),
                'reason' => $response->getReasonPhrase()
        ];
    }

    /**
     * 
     * @param String $query
     * @return type
     */
    public function createSearchJob(String $query) {
        return $this->makeApiRequest('POST','',$query);
    }

    /**
     * 
     * @param String $jobid
     * @return type
     */
    public function getSearchJobStatus(String $jobid) {
        return $this->makeApiRequest('GET',$jobid);
    }

    /**
     * 
     * @param String $jobid
     * @param int $offset
     * @param int $limit
     * @param OutputInterface $output
     * @return type
     */
    public function getQueryResults(String $jobid,int $offset, int $limit,OutputInterface $output=null) {
        return $this->makeApiRequest('GET',$jobid . '/messages?offset=' . $offset . '&limit='.$limit,null,$output);
    } 
}
