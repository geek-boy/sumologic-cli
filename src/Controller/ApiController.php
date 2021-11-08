<?php

namespace App\Controller;

include_once(__DIR__.'/../../config/constants.php');

use Symfony\Component\Yaml\Yaml;
use GuzzleHttp\Client as GuzzHttpClient;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


/**
 * ApiController Class.
 *
 * @package App\Controller\ApiController
 */
class ApiController extends AbstractController {

  /**
   * 
   * @var Object
   *   \GuzzleHttp\Client
   */
  private $httpclient;

  /**
   * 
   * @var Object
   *   \GuzzleHttp\Cookie\CookieJar
   */
  private $jar;

  /**
   * 
   * @var String
   *   Builds string for result.
   */
  private $output = NULL;

  /**
   * 
   * @var Object
   *   @todo unknown
   */
  private $downloadedBytesProgressBar = NULL;

  /**
   *  @todo description
   * 
   * @param String $default_creds_path
   *   Gets default Sumologic private login details
   * @param String $sumologic_api_end_point
   *   Provides rest API end point.
   */
  public function __construct($default_creds_path, $sumologic_api_end_point) {
    $this->output = NULL;
    $this->downloadedBytesProgressBar = NULL;
    
    $creds = Yaml::parseFile($default_creds_path);

    // Creates HTTP object so it can be used in the class.
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
    // $this->jar = new \GuzzleHttp\Cookie\CookieJar;
  }

  /**
   *  @todo description
   * 
   * @param String $method
   *    @todo unknown. 
   * @param String $api_end_point
   *    @todo could this be replaced by $sumologic_api_end_point.
   * @param type $data
   *    @todo unknown.
   * @param bool $return_results_as_array
   *    Returns result as and array.
   * @param OutputInterface $output
   *    Symfony\Component\Console\Output\OutputInterface.
   * @return array
   *    Returns array object
   */
  public function makeApiRequest(
          String $method,
          String $api_end_point = '',
          $data = null,
          bool $return_results_as_array = false,
          OutputInterface $output = null    // Output progress if not null
  ) {
    $path = (empty($api_end_point)) ? '' : '/' . $api_end_point;
    $full_uri = SUMOLOGIC_JOB_SEARCH_API . $path;
    $this->output = $output;

    if (!empty($this->output)) {
      $this->downloadedBytesProgressBar = new ProgressBar($this->output, 0);
    }

    // Send the request.
    $response = null;
    $options = [];
    $downloadTotal = 0;
    $downloadedBytes = 0;
    $uploadTotal = 0;
    $uploadedBytes = 0;

    if ($data !== null) {
      $options = ['body' => $data];
      $response = $this->httpclient->request($method, $full_uri, $options);
    } else {
      if (!empty($this->output)) {
        $this->downloadedBytesProgressBar->setFormat('api_controller_downloaded_bytes');
        $this->downloadedBytesProgressBar->start();
        $this->downloadedBytesProgressBar->setMessage("full_uri: " . $full_uri);
        $this->downloadedBytesProgressBar->display();
      }

      $response = $this->httpclient->request($method, $full_uri, [
          'headers' => ['Accept: application/json'],
          'progress' => function (
                  $downloadTotal,
                  $downloadedBytes,
                  $uploadTotal,
                  $uploadedBytes
          ) {
            if (!empty($this->output)) {
              $timezone = date_default_timezone_get();
              $date = date('Y-m-d h:i:s a', time());
              $this->downloadedBytesProgressBar->clear();
              $this->downloadedBytesProgressBar->setMessage($date . ' ' . $timezone, 'date');
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
        'body' => json_decode($response->getBody(), $return_results_as_array),
        'reason' => $response->getReasonPhrase()
    ];
  }

  /**
   *  @todo description
   * 
   * @param String $query
   *   @todo unknown.
   * @return array
   *   @todo unknown.
   */
  public function createSearchJob(String $query) {
    return $this->makeApiRequest('POST', '', $query);
  }

  /**
   *  @todo description
   * 
   * @param String $jobid
   *   @todo unknown.
   * @return array
   *   @todo unknown.
   */
  public function getSearchJobStatus(String $jobid) {
    return $this->makeApiRequest('GET', $jobid);
  }

  /**
   *  @todo description
   * 
   * @param String $jobid // Job ID for the query
   * @param int $offset   // Return records starting at this offset    
   * @param int $limit    // The number of records starting at offset to return 
   * @param bool $return_messages // Return results as 'messages' or as 'records'
   * @param bool $return_results_as_array // Return results as an array rather than as an object
   * @param OutputInterface $output=null  // OutputInterface instance to print out to console
   * 
   * @return array
   *   @todo unknown.
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
