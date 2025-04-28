 
<?php
/**
 * Database Connection File
 * 
 * This file handles the database connection for the Digital Service Billing App
 * It uses PDO for secure database operations with prepared statements
 */

// Prevent direct access to this file
if (!defined('APP_ACCESS')) {
    die('Direct access to this file is not allowed.');
}

// Database configuration
$db_config = [
    'host'     => 'localhost',      // Database host (change if needed)
    'dbname'   => 'digital_billing', // Database name
    'username' => 'dbuser',         // Database username (change to your DB username)
    'password' => 'your_secure_password', // Database password (change to your DB password)
    'charset'  => 'utf8mb4',        // Unicode character support
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => true, // Persistent connections for better performance
    ],
];

// Global database connection variable
$db = null;

/**
 * Get database connection
 * 
 * @return PDO Database connection object
 */
function getDbConnection() {
    global $db, $db_config;
    
    // If connection already exists, return it
    if ($db instanceof PDO) {
        return $db;
    }
    
    try {
        // Create DSN (Data Source Name)
        $dsn = "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset={$db_config['charset']}";
        
        // Create PDO instance
        $db = new PDO($dsn, $db_config['username'], $db_config['password'], $db_config['options']);
        
        // Set timezone for database queries
        $timezone = date('P');
        $db->exec("SET time_zone='$timezone'");
        
        return $db;
    } catch (PDOException $e) {
        // Log error (in production, don't display error details to users)
        error_log("Database Connection Error: " . $e->getMessage());
        
        // For development
        if (defined('DEV_MODE') && DEV_MODE === true) {
            die("Connection failed: " . $e->getMessage());
        } else {
            // For production
            die("A database error occurred. Please try again later or contact support.");
        }
    }
}

/**
 * Execute a query with parameters
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind to the query
 * @return PDOStatement The statement after execution
 */
function dbQuery($sql, $params = []) {
    $db = getDbConnection();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/**
 * Get a single row from the database
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind to the query
 * @return array|null The fetched row or null if not found
 */
function dbFetchRow($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetch();
}

/**
 * Get multiple rows from the database
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind to the query
 * @return array The fetched rows
 */
function dbFetchAll($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt->fetchAll();
}

/**
 * Insert data into a table
 * 
 * @param string $table Table name
 * @param array $data Associative array of column => value pairs
 * @return int|bool The last insert ID or false on failure
 */
function dbInsert($table, $data) {
    $db = getDbConnection();
    
    $columns = implode(', ', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute(array_values($data));
        return $db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Database Insert Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update data in a table
 * 
 * @param string $table Table name
 * @param array $data Associative array of column => value pairs
 * @param string $whereColumn Column name for the WHERE clause
 * @param mixed $whereValue Value for the WHERE clause
 * @return int|bool Number of rows affected or false on failure
 */
function dbUpdate($table, $data, $whereColumn, $whereValue) {
    $db = getDbConnection();
    
    $setClauses = [];
    foreach (array_keys($data) as $column) {
        $setClauses[] = "$column = ?";
    }
    $setClause = implode(', ', $setClauses);
    
    $sql = "UPDATE $table SET $setClause WHERE $whereColumn = ?";
    
    try {
        $stmt = $db->prepare($sql);
        $values = array_values($data);
        $values[] = $whereValue;
        $stmt->execute($values);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Database Update Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete data from a table
 * 
 * @param string $table Table name
 * @param string $whereColumn Column name for the WHERE clause
 * @param mixed $whereValue Value for the WHERE clause
 * @return int|bool Number of rows affected or false on failure
 */
function dbDelete($table, $whereColumn, $whereValue) {
    $db = getDbConnection();
    
    $sql = "DELETE FROM $table WHERE $whereColumn = ?";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute([$whereValue]);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Database Delete Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Begin a database transaction
 */
function dbBeginTransaction() {
    $db = getDbConnection();
    $db->beginTransaction();
}

/**
 * Commit a database transaction
 */
function dbCommit() {
    global $db;
    if ($db instanceof PDO) {
        $db->commit();
    }
}

/**
 * Rollback a database transaction
 */
function dbRollback() {
    global $db;
    if ($db instanceof PDO) {
        $db->rollBack();
    }
}
?>