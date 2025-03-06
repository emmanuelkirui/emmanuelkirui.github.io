<?php 
// DB credentials
define('DB_HOST', 'sql105.infinityfree.com');
define('DB_USER', 'if0_37772405');
define('DB_PASS', 'hMCWvBjYOKjDE');
define('DB_NAME', 'if0_37772405_cps');

// Establish database connection
try {
    $dbh = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, 
        DB_USER, 
        DB_PASS, 
        array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'")
    );
} catch (PDOException $e) {
    // Check if client prefers JSON (via Accept header or query parameter)
    $prefersJson = false;
    
    // Check Accept header
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        $prefersJson = true;
    }
    
    // Check for json=1 query parameter as alternative
    if (isset($_GET['json']) && $_GET['json'] == '1') {
        $prefersJson = true;
    }

    if ($prefersJson) {
        // JSON response
        header('Content-Type: application/json');
        $errorResponse = array(
            'status' => 'error',
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'timestamp' => date('Y-m-d H:i:s')
        );
        exit(json_encode($errorResponse));
    } else {
        // Plain text response (original format)
        exit("Error: " . $e->getMessage());
    }
}
?>
