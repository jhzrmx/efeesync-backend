@echo off
SET PHP_FCGI_MAX_REQUESTS=0
:start
php-cgi.exe -b 127.0.0.1:9000
goto start