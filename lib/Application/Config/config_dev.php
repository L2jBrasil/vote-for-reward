<?php

if (count(get_included_files()) == 1) {
    die("Direct access not permitted.");
}
return array(
        'sys.env' => "dev",
        'db.host' => "localhost",
        'db.name' => "dbname",
        'db.user' => "root",
        'db.pwd' => ""
    );