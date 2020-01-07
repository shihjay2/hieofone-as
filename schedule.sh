#!/bin/bash
# Run scheduler
while [ true ]
do
  php /var/www/as/artisan schedule:run
  sleep 60
done
