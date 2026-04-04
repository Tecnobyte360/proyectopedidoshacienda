#!/bin/bash

cd /var/www/html

php-fpm -D
nginx -g "daemon off;"
