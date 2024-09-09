<?php
declare(strict_types=1);

namespace Bingo\Src\Services;

use PDO;
use PDOException;

class PdoConnection
{
    const MATCHED_TABLE_NAME = 'notifications';
    const LIKED_TABLE_NAME = 'notifications';
    const LAST_NOTIFICATION_TABLE_NAME = 'lastNotification';
    const CARDS_TABLE_NAME = 'cards';
    const USERS_TABLE_NAME = 'users';
    const GAMES_TABLE_NAME = 'games';
    const JOINED_TABLE_NAME = 'notifications';
    const NOTIFICATIONS_TABLE_NAME = 'notifications';
    const OPTIONS_TABLE = 'options';

    private $connection;

    public function __construct() {
        $developmentMode = (getenv('MODE') === 'dev');

        try {
            if($developmentMode) {
                $host = getenv('DB_HOST');
                $username   = getenv('DB_USERNAME');
                $password   = getenv('DB_PASSWORD');
                $database   = getenv('DB_DATABASE');

                $this->connection = new PDO('mysql:host=' . $host . ';dbname=' . $database, $username, $password);
            }
            else {
                $host = 'localhost';
                $port = '3306';
                $username   = '';
                $password   = '';
                $database   = '';

                $this->connection = new PDO('mysql:unix_socket=/run/mysqld/mysqld.sock;host='. $host . ';port=' . $port . ';dbname=' . $database, $username, $password);
            }
        } catch (PDOException $e) {
            // Trigger an error if the connection fails cos what's the point of carrying on?
            trigger_error('Connection failed: ' . $e->getMessage(), E_USER_ERROR);
        }
    }

    /**
     * @return null|PDO
     */
    public function getConnection()
    {
        return $this->connection;
    }
}
