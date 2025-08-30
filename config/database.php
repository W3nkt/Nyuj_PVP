<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'battle_bidding';
    private $username = 'ckateng';
    private $password = '7pz4wkMsc5Obm3pb';
    private $conn;

    public function connect() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->host . ';dbname=' . $this->db_name,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $e) {
            // Log error for debugging
            error_log('Database Connection Error: ' . $e->getMessage());
            
            // Display user-friendly error in development
            if (isset($_ENV['APP_DEBUG']) || !empty($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'localhost') {
                die('<div style="background: #f8d7da; color: #721c24; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 20px; font-family: Arial, sans-serif;">
                    <h3>Database Connection Error</h3>
                    <p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
                    <p><strong>Host:</strong> ' . $this->host . '</p>
                    <p><strong>Database:</strong> ' . $this->db_name . '</p>
                    <p><strong>Username:</strong> ' . $this->username . '</p>
                    <hr>
                    <p><strong>Troubleshooting:</strong></p>
                    <ul>
                        <li>Check if MySQL/MariaDB server is running</li>
                        <li>Verify database credentials in config/database.php</li>
                        <li>Ensure the database "' . $this->db_name . '" exists</li>
                        <li>Check if the user has proper permissions</li>
                    </ul>
                    </div>');
            } else {
                die('Database connection failed. Please contact the administrator.');
            }
        }
        
        return $this->conn;
    }
}
?>