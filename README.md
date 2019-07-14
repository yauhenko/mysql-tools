# mysql-tools
This tool can create and restore mysql databases dumps (structure and data)

Basic usage
-----------
* `$ mysql-tools dump user:password@localhost/dbname dbname.dump`
* `$ mysql-tools restore user:password@localhost/dbname dbname.dump`

Options
-------
* `-d`, `--data-only` - Dump/Restore only data
* `-s`, `--struct-unly` - Dump/Restore only structure

Full command line options
-------------------------
```
MySQL Tools 1.0.0

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  dump     Create dump
  help     Displays help for a command
  list     Lists commands
  restore  Restore dump
```

Build
-----
* Just set `phar.readonly = Off` in your `php.ini`
* Run `$ php build.php`

Requirements
------------
* PHP 7.2+
* ext-mysql
