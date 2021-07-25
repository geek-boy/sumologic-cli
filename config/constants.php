<?php
use Symfony\Component\Console\Helper\ProgressBar;

!defined("APP_COMMAND_NAME") && define("APP_COMMAND_NAME", "sumologic-cli");
!defined("DEFAULT_CRED_FILE_PATH") && define("DEFAULT_CRED_FILE_PATH",getenv("HOME") . "/.sumologic-creds.yml");
!defined("SUMOLOGIC_JOB_SEARCH_API") && define("SUMOLOGIC_JOB_SEARCH_API","https://api.sumologic.com/api/v1/search/jobs");
!defined("DEFAULT_RESULTS_DIR_PATH") && define("DEFAULT_RESULTS_DIR_PATH",getenv("HOME"));

ProgressBar::setFormatDefinition('request_query_record_progress', 'Getting records... %recordCount% to %upperLimit%');
ProgressBar::setFormatDefinition('request_query_file_size_progress', 'File size is %logFileSize%');
ProgressBar::setFormatDefinition('api_controller_downloaded_bytes', '%date%: Downloading - Bytes downloaded %downloadedBytes%');