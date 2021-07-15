<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use GuzzleHttp\Client as GuzzHttpClient;
use GuzzleHttp\Cookie\CookieJarInterface;
use GuzzleHttp\Psr7\Request as GuzzRequest;
use Symfony\Component\Console\Helper\ProgressBar;



class ApiController extends AbstractController /*extends SymfonyController*/
{
    private $httpclient;
    private $jar;
    private $output;
    private $downloadedBytesProgress;

    public function __construct($default_creds_path, $sumologic_api_end_point)
    {

        $this->output = NULL;
        $this->downloadedBytesProgressBar = NULL;
        ProgressBar::setFormatDefinition('downloaded_bytes', '%date%: Downloading - Bytes downloaded %downloadedBytes%');
        
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

    public function makeApiRequest(String $method, String $api_end_point = '', $data = null) {

        //$this->downloadedBytesProgressBar = NULL;
        assert_options(ASSERT_CALLBACK, 'progress_assert_handler');
        assert_options(ASSERT_BAIL, true);

        assert($this->downloadedBytesProgressBar !== NULL);

        $path = (empty($api_end_point)) ? '' : '/' . $api_end_point; 
        $full_uri = SUMOLOGIC_JOB_SEARCH_API . $path;

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
        }
         else {

            // if ($this->setDownloadedBytesProgressBar != NULL) {
                
                $this->downloadedBytesProgressBar->setFormat('downloaded_bytes');
                $this->downloadedBytesProgressBar->start();
            // }
            
            $response = $this->httpclient->request($method,$full_uri,[
                'headers' => ['Accept: application/json'],
                'progress' => function(
                    $downloadTotal,
                    $downloadedBytes,
                    $uploadTotal,
                    $uploadedBytes
                ) {
                    if ($this->output != NULL) {
                        $timezone = date_default_timezone_get();
                        $date = date('Y-m-d h:i:s a', time());
                        $this->downloadedBytesProgressBar->clear();
                        $this->downloadedBytesProgressBar->setMessage($date. ' ' . $timezone, 'date');
                        $this->downloadedBytesProgressBar->setMessage($downloadedBytes, 'downloadedBytes');
                        $this->downloadedBytesProgressBar->advance();
                        $this->downloadedBytesProgressBar->display();
                        
                    }
                    $this->downloadedBytesProgressBar->finish();
                    

                },
                 
                
                ]);
        }

        return ['status_code' => $response->getStatusCode(),
                'body' => json_decode($response->getBody()),
                'reason' => $response->getReasonPhrase()
        ];
    }

    public function createSearchJob(String $query) {
        return $this->makeApiRequest('POST','',$query);
    }

    public function getSearchJobStatus(String $jobid) {
        return $this->makeApiRequest('GET',$jobid);
    }

    public function getQueryResults(String $jobid,int $offset, int $limit) {
        return $this->makeApiRequest('GET',$jobid . '/messages?offset=' . $offset . '&limit='.$limit);
    }

    public function setOutput(OutputInterface $output) {
        $this->output = $output;
        $this->downloadedBytesProgressBar = new ProgressBar($this->output, 0);
    }

    public function setDownloadedBytesProgressBar($progressBar) {
        $this->downloadedBytesProgressBar = $progressBar;
    }

    public function progress_assert_handler($file, $line, $code, $desc = null)
    {
        echo "Assert failure";
    }
   
    
}