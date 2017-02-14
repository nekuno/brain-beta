<?php

define('IS_WINDOWS', strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
define('NEO4J_VERSION', '3.0.7');
define('NEO4J_PATH', 'vendor' . DIRECTORY_SEPARATOR . 'neo4j-community-' . NEO4J_VERSION);
define('NEO4J_BIN', NEO4J_PATH . DIRECTORY_SEPARATOR . 'bin');
define('NEO4J_FILE', IS_WINDOWS ? 'windows.zip' : 'unix.tar.gz');

if (!is_dir(NEO4J_PATH)) {
    exec('wget http://neo4j.com/artifact.php?name=neo4j-community-' . NEO4J_VERSION . '-' . NEO4J_FILE . ' -Ovendor/' . NEO4J_FILE);
    IS_WINDOWS ? exec('unzip vendor/' . NEO4J_FILE . ' -d vendor') : exec('tar xvzf vendor/' . NEO4J_FILE . ' -C vendor');
    exec('rm vendor/' . NEO4J_FILE);
    exec("sed -i 's/#dbms.connector.http.address=0.0.0.0:7474/dbms.connector.http.address=0.0.0.0:7475/g' " . NEO4J_PATH . '/conf/neo4j.conf');
    exec("sed -i 's/dbms.connector.https.address=localhost:7473/#dbms.connector.https.address=localhost:7473/g' " . NEO4J_PATH . '/conf/neo4j.conf');
    exec('mkdir ' . NEO4J_PATH . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'dbms');
    exec('echo nekuno:SHA-256,F9C128A993975A8C867C996FBAD87CE1E557D9748B5451FF6F5257C19F56BA04,5A9123556B2846896AF655440E3966EC: > ' . NEO4J_PATH . '/data/dbms/auth');
}

$output_ssh = shell_exec('ssh vagrant@local.nekuno.com -o LogLevel=verbose ./../../vagrant/brain/' . NEO4J_PATH . '/bin/neo4j restart');

if ($output_ssh === null) {
    if (IS_WINDOWS) {
        exec(NEO4J_BIN . DIRECTORY_SEPARATOR . 'neo4j.bat status', $outputStatus, $code);
        echo implode("\n", $outputStatus) . "\n";
        if ($code !== 0) {
            echo shell_exec(NEO4J_BIN . DIRECTORY_SEPARATOR . 'neo4j.bat install-service');
            exec(NEO4J_BIN . DIRECTORY_SEPARATOR . 'neo4j.bat start', $outputStart, $code);
            echo implode("\n", $outputStart) . "\n";
            if ($code !== 0) {
                trigger_error('Error starting Neo4j');
                die;
            }
        }
    } else {
        exec(NEO4J_BIN . DIRECTORY_SEPARATOR . 'neo4j start', $outputStart, $code);
        echo implode("\n", $outputStart) . "\n";
        if ($code !== 0) {
            trigger_error('Error starting Neo4j');
            die;
        }
    }
}
