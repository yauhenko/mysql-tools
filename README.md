# mysql-tools
This tool can create and restore mysql databases dumps (structure and data)

Basic usage
-----------
* `$ mysql-tools dump -u root -p secret -b dbname output.dump`
* `$ mysql-tools restore -u root -p secret -b dbname input.dump`
* `$ mysql-tools list`
* `$ mysql-tools help <command>`
* `$ sudo ./mysql-tools.phar install`

Options
-------
* `-u`, `--user[=root]` - Username
* `-p`, `--pass[=secret]` - Password
* `-b`, `--database[=somedb]` - Database name
* `--host[=localhost]` - Hostname
* `--port[=3306]` - Port
* `-d`, `--data-only` - Dump/Restore only data
* `-s`, `--struct-only` - Dump/Restore only structure
* `-h`, `--help` - Display this help message
* `-q`, `--quiet` - Do not output any message
* `-V`, `--version` - Display this application version
* `--ansi` - Force ANSI output
* `--no-ansi` - Disable ANSI output
* `-n`, `--no-interaction` - Do not ask any interactive question
* `-v|vv|vvv`, `--verbose` - Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Build
-----
* Just set `phar.readonly = Off` in your `php.ini`
* Run `$ php build.php`

Requirements
------------
* PHP 7.2+
* ext-mysql
* ext-json
