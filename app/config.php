<?php
// Environment variables override these local defaults. The app directory is blocked from HTTP access.
return [
    'env'            => getenv('APP_ENV') ?: 'local',
    'url'            => rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/'),
    'db_host'        => getenv('DB_HOST') ?: 'mysql',
    'db_name'        => getenv('DB_NAME') ?: 'project_db',
    'db_user'        => getenv('DB_USER') ?: 'project_user',
    'db_password'    => getenv('DB_PASSWORD') ?: (getenv('MYSQL_PASSWORD') ?: ''),
    'mail_transport' => getenv('MAIL_TRANSPORT') ?: 'log', // log: writes storage/mail.log; mail: server MTA
    'mail_from'      => getenv('MAIL_FROM') ?: 'no-reply@example.com',
];
