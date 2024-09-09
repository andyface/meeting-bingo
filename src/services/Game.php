<?php
declare(strict_types=1);

namespace Bingo\Src\Services;

use Bingo\Src\Controllers\BingoController;
use PDO;

class Game
{
    private $connection;

    private $gameId;

    private $type;

    public function __construct(PDO $connection, string $gameId)
    {
        $this->connection = $connection;
        $this->gameId = $gameId;
        $this->type = $this->findGameType();
    }

    private function findGameType() : ?string
    {
        // Get the game type from the games table
        $statement = $this->connection->prepare('SELECT type FROM ' . PdoConnection::GAMES_TABLE_NAME . ' WHERE gameId = :gameId');
        $statement->bindParam(':gameId', $this->gameId, PDO::PARAM_STR);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result['type'];
    }

    /**
     * @return int
     */
    public function getGameId(): string
    {
        return $this->gameId;
    }

    /**
     * @return string
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    public function getOpponents($currentUser)
    {
        // Get the game type from the games table
        $statement = $this->connection->prepare('
            SELECT u.id, u.username 
            FROM ' . PdoConnection::NOTIFICATIONS_TABLE_NAME . ' n 
            LEFT JOIN ' . PdoConnection::USERS_TABLE_NAME . ' u on n.userId = u.id 
            WHERE n.gameId = :gameId 
            AND n.userId != :userId
            AND n.type = :type
        ');
        $statement->bindParam(':gameId', $this->gameId, PDO::PARAM_STR);
        $statement->bindParam(':userId', $currentUser, PDO::PARAM_INT);
        $statement->bindValue(':type', BingoController::NOTIFICATION_JOIN, PDO::PARAM_STR);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isGame(): bool
    {
        return true;
    }
}