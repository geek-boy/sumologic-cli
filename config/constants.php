<?php
use Symfony\Component\Console\Helper\ProgressBar;

define("APP_COMMAND_NAME", "sumologic-cli");
define("DEFAULT_CRED_FILE_PATH",getenv("HOME") . "/.sumologic-creds.yml");
define("SUMOLOGIC_JOB_SEARCH_API","https://api.sumologic.com/api/v1/search/jobs");
define("DEFAULT_RESULTS_DIR_PATH",getenv("HOME"));

ProgressBar::setFormatDefinition('request_query_record_progress', 'Getting records... %recordCount% to %upperLimit%');
ProgressBar::setFormatDefinition('request_query_file_size_progress', 'File size is %logFileSize%');
ProgressBar::setFormatDefinition('api_controller_downloaded_bytes', '%date%: Downloading - Bytes downloaded %downloadedBytes%');