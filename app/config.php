<?php
// Environment variables override these local settings. The app directory is protected by Apache access rules.
return [
 'env' => getenv('APP_ENV') ?: 'local',
 'url' => rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/'),
 'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Karachi',
 'db_driver' => getenv('DB_DRIVER') ?: 'mysql',
 'db_host' => getenv('DB_HOST') ?: 'mysql',
 'db_name' => getenv('DB_NAME') ?: 'project_db',
 'db_user' => getenv('DB_USER') ?: 'project_user',
 'db_password' => getenv('DB_PASSWORD') ?: getenv('MYSQL_PASSWORD') ?: '',
 'mail_transport' => getenv('MAIL_TRANSPORT') ?: 'log', // log: local only; mail: configured cPanel MTA
 'mail_from' => getenv('MAIL_FROM') ?: 'no-reply@example.com',
 'key' => getenv('APP_KEY') ?: trim(is_file(__DIR__.'/../storage/app.key') ? file_get_contents(__DIR__.'/../storage/app.key') : ''),
];
