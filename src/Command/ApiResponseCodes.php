<?php

namespace App\Command;

/**
 * Description of ApiResponseCodes
 * Status codes defined by SumoLogic API.
 * https://help.sumologic.com/APIs/Search-Job-API/About-the-Search-Job-API#errors
 */
class ApiResponseCodes {

  /**
   * 
   * @param type $href
   * @return type
   */
  protected static function getQueryJobID($href) {
    $matches = null;
    $pattern = str_replace("/", "\/", SUMOLOGIC_JOB_SEARCH_API);
    $pattern = '/' . $pattern . '\/' . '([0-9A-Za-z]+)' . '/';
    preg_match($pattern, $href, $matches);

    if (count($matches) == 2) {
      return $matches[1];
    }

    return null;
  }

  /**
   * 
   * @param type $response
   * @return type
   */
  public static function getResponse($response, $output) {

    $jobId = null;

    switch ($response['status_code']) {
      case 202:
        $jobId = self::getQueryJobID($response['body']->link->href);
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

    if ($jobId === null) {
      $output->writeln("<error>Error retrieving Sumologic Job ID - Exiting</error>");
      return Command::FAILURE;
    } else {
      return $jobId;
    }
  }

}
