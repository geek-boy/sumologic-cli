# Sumologic CLI

Tool for running Sumologic queries from the command line.
This application allows you to run queries against the Sumologic Search Job API (https://help.sumologic.com/APIs/Search-Job-API/About-the-Search-Job-API). It will download resuts locally to your home directory. 

## Installation
After cloning the repository then install using composer from the code directory:

```
composer install
```

Once this is done you should be good to use the application by using: 

```
./sumologic-cli
```

The main command, `query:run`, makes a request to the Sumologic Job Search API to run a Query and save results locally.
The search query for the Sumologic Job Search API can be provided either from a file, if it is more complex, or by using the `--search-query` option for simpler search queries.  

Examples ways to run the `query:run` command:

    * sumologic-cli query:run /home/user/query_file 2021-06-05T11:09:00 2021-06-05T12:09:00
    * sumologic-cli query:run --end="-7days" /home/user/query_file.txt 2021-06-05T11:09:00
    * sumologic-cli query:run --format=csv /home/user/query_file 2021-06-05T11:09:00 2021-06-05T12:09:00
    * sumologic-cli query:run --search-query="namespace=agoorah.apache-access" 2021-06-05T11:09:00 2021-06-05T12:09:00
    * sumologic-cli query:run --search-query="namespace=agoorah.apache-access" --start="-2hours" --end="-1hour"
    * sumologic-cli query:run --search-query="namespace=agoorah.apache-access" --start="-2hours" --end="-1hour" --format=tab
    * sumologic-cli query:run --search-query="namespace=agoorah.apache-access" --start="-2hours"
    * sumologic-cli query:run --search-query="namespace=agoorah.apache-access" --fields-only --start="-2hours" --end="-1hour"
    * sumologic-cli query:run --search-query="namespace=agoorah.apache-access" --fields-only --start="-2hours" --end="-1hour" --format=tab

See https://www.php.net/manual/en/class.datetimeinterface.php for ISO Date format.

See https://www.php.net/manual/en/datetime.formats.relative.php for valid relative time formats.
