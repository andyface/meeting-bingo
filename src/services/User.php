<?php
declare(strict_types=1);

namespace Bingo\Src\Services;

use PDO;

class User
{
    const XP_AMOUNT = [
        'bingo' => 25,
        Option::COMMON => 5,
        Option::RARE => 10,
        Option::LEGENDARY => 25,
        Option::FREE => 0,
        Option::DEAD => 0
    ];

    const LEVEL_XP_MAX = [
        1 => 25,
        2 => 100,
        3 => 250,
        4 => 700,
        5 => 1500,
        6 => 2500,
        7 => 5000,
        8 => 7500,
        9 => 10000,
        10 => 15000
    ];

    /**
     * @var PDO
     */
    private $connection;
    /**
     * @var int ID of the user
     */
    private $userId = null;

    private $username = null;

    private $xp = null;

    public function __construct(PDO $connection, ?int $userId)
    {
        $this->connection = $connection;
        $this->userId = $userId;

        if($this->userId) {
            $this->username = $this->fetchUsername();
            $this->xp = $this->fetchXp();
        }
    }

    public function fetchUsername() {
        $statement = $this->connection->prepare('SELECT username FROM users WHERE id = :userId');
        $statement->bindParam(':userId', $this->userId, PDO::PARAM_INT);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result['username'];
    }

    public function fetchXp() {
        $statement = $this->connection->prepare('SELECT xp FROM users WHERE id = :userId');
        $statement->bindParam(':userId', $this->userId, PDO::PARAM_INT);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result['xp'];
    }

    public function updateXp() {
        $this->xp = $this->fetchXp();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->userId;
    }

    /**
     * @return mixed
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @return mixed
     */
    public function getXp()
    {
        return $this->xp;
    }

    public function getLevel()
    {
        foreach (self::LEVEL_XP_MAX as $key => $max) {
            if($this->getXp() < $max) {
                return $key;
            }
        }
    }

    public function getLevelMax()
    {
        return self::LEVEL_XP_MAX[$this->getLevel()];
    }

    public function isUser(): bool
    {
        if($this->userId && $this->getUsername()) {
            return true;
        }

        return false;
    }

    /**
     * TODO
     * Earn skills to refresh an option or something
     * Ability to mess with other persons game, locking off a square
     * Fix it so that the XP bar fills to 100% then updates to show progress of next level, not full XP progress
     */
}