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

This command makes a request to the Sumologic Job Search API to run a Query and save results locally.

Examples ways to run the command:

* `sumologic-cli /home/user/query_file 2021-06-05T11:09:00 2021-06-05T12:09:00`
* `sumologic-cli /home/user/query_file.txt 2021-06-05T11:09:00 --end="-7days"`
* `sumologic-cli --query="namespace=agoorah.apache-access" 2021-06-05T11:09:00 2021-06-05T12:09:00`
* `sumologic-cli --query="namespace=agoorah.apache-access" --start="2hours" --end="1hour"`
* `sumologic-cli --query="namespace=agoorah.apache-access" --start="2hours"`
* `sumologic-cli --query="namespace=agoorah.apache-access" --fields-only --start="2hours" --end="1hour"`

See https://www.php.net/manual/en/datetime.formats.relative.php for valid relative time formats.