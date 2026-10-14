

cd /home/workspace/my-project
EXT=$(dirname $(find $PWD/phpext -name pdo_mysql.so))
php -d extension=$EXT/mysqlnd.so -d extension=$EXT/pdo_mysql.so -m | grep -i mysql

php -d extension=$EXT/mysqlnd.so -d extension=$EXT/mysqli.so -d extension=$EXT/pdo_mysql.so -S 0.0.0.0:8080 -t public

https://689584ea98b0-0af412bf-8080.ws6.app/

   cd /home/workspace/my-project
   ls public/config phpext

    cd /home/workspace/my-project
   EXT=$(dirname $(find $PWD/phpext -name pdo_mysql.so))
   php -d extension=$EXT/mysqlnd.so -d extension=$EXT/mysqli.so -d extension=$EXT/pdo_mysql.so -S 0.0.0.0:8080 -t public