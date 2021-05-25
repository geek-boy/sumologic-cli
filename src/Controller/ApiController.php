<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;
use GuzzleHttp\Client as GuzzHttpClient;
use GuzzleHttp\Cookie\CookieJarInterface;
use GuzzleHttp\Psr7\Request as GuzzRequest;


class ApiController extends AbstractController /*extends SymfonyController*/
{
    private $httpclient;
    private $jar;

    public function __construct(String $creds_path, String $end_point)
    {

        
        // $this->creds = Yaml::parseFile($creds_path);
        $creds = Yaml::parseFile($creds_path);
        $sumologic_api_end_point = $end_point;

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
        $path = (empty($api_end_point)) ? '' : '/' . $api_end_point; 
        $full_uri = SUMOLOGIC_JOB_SEARCH_API . $path;

        // Send the request.
        $response = null;
        $options = [];
        if($data !== null) {
            $options = ['body' => $data];
            $response = $this->httpclient->request($method,$full_uri,$options);
        }
         else {
            $response = $this->httpclient->request($method,$full_uri,['headers' => ['Accept: application/json']]);
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

    /*
    public function getCurrentUserUUID() {
        return $this->current_user_uuid;
    }

    public function getApplicationList() {
        return $this->makeApiRequest('GET','/applications');
    }

    public function getOrganizationsList() {
        return $this->makeApiRequest('GET','/organizations');
    }
    public function getUserTeams() {
        return $this->makeApiRequest('/teams');
    }
    public function removeFromOrganization($uuid) {
        return $this->makeApiRequest('POST','/organizations/'.$uuid.'/actions/leave');
    }
    public function getEmployeeAppEnvironments() {
        return $this->makeApiRequest('GET','/applications/' . $this->employee_acc_app_uuid . '/environments');
    }
    public function getEmployeeAppTasks() {
        return $this->makeApiRequest('GET','/applications/' . $this->employee_acc_app_uuid . '/tasks');
    }
    public function enableEmployeeLiveDev($environment_id,$enable=true) {
        if ($enable) {
            // Enable Live Development
            return $this->makeApiRequest('POST','/environments/'.$environment_id.'/livedev/actions/enable');
        }

        // Disable Live Development
        return $this->makeApiRequest('POST','/environments/'.$environment_id.'/livedev/actions/disable');
    }
    */
}