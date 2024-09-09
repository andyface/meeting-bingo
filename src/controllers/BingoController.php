<?php
declare(strict_types=1);

namespace Bingo\Src\Controllers;

use Bingo\Src\Helpers\BingoHelper;
use Bingo\Src\Services\BingoOptions;
use Bingo\Src\Services\Option;
use Bingo\Src\Services\PdoConnection;
use Bingo\Src\Services\User;
use DateTime;
use DateTimeZone;
use PDO;

class BingoController
{
    private const NOTIFICATION_MATCH = 'match';
    private const NOTIFICATION_LIKE = 'like';
    private const NOTIFICATION_DISLIKE = 'dislike';
    public const NOTIFICATION_JOIN = 'join';
    private const NOTIFICATION_REFRESH = 'refresh';
    private const NOTIFICATION_BINGOED = 'bingo';

    private PDO $connection;
    private User $user;
    private ?int $userId;
    private ?string $gameId;
    private string $dateTime;

    public function __construct(PDO $connection, ?int $userId, ?string $gameId) {
        $this->connection = $connection;
        $this->user = new User($connection, $userId);
        $this->userId = $userId;
        $this->gameId = $gameId;
        $this->dateTime = (new DateTime('now', new DateTimeZone('+0000')))->format('Y-m-d H:i:s.v');
    }

    public function saveUser(array $request): array
    {
        $userName = $request['name'];

        $statement = $this->connection->prepare('
                    SELECT id 
                    FROM ' . PdoConnection::USERS_TABLE_NAME . ' 
                    WHERE username = :userName
                ');
        $statement->bindValue(':userName', $userName, PDO::PARAM_STR);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        if (empty($result)) {
            $statement = $this->connection->prepare('
                        INSERT INTO ' . PdoConnection::USERS_TABLE_NAME . ' 
                        (username, xp) 
                        VALUES (:userName, 0)
                    ');
            $statement->bindValue(':userName', $userName, PDO::PARAM_STR);
            if ($statement->execute()) {
                $this->userId = (int) $this->connection->lastInsertId();
            } else {
                $errors[] = $statement->errorInfo();
                $this->userId = null;
            }
        } else {
            $this->userId = $result['id'];
        }

        if (empty($errors)) {
            // Set cookie to store userId with an expiry of 30 days
            setCookie('userId', (string) $this->userId, time() + 60 * 60 * 24 * 30, '/bingo');

            $response = ['status' => 'success', 'userId' => $this->userId];
        } else {
            $response = ['status' => 'failure', 'errors' => $errors];
        }

        return $response;
    }

    public function createGame(array $request): array
    {
        $type = $request['type'] ?: NULL;

        if(isset($type)) {
            setcookie('type', $type, time() + 60 * 60 * 24 * 30, '/bingo');
        }
        setcookie('settingsSaved', "1", 0, '/bingo');

        $newGameId = uniqid();

        $statement = $this->connection->prepare('
                    INSERT INTO ' . PdoConnection::GAMES_TABLE_NAME . ' 
                    (gameId, created, type) 
                    VALUES (:gameId, :datetime, :type)
                ');
        $statement->bindValue(':gameId', $newGameId, PDO::PARAM_STR);
        $statement->bindValue(':datetime', $this->dateTime, PDO::PARAM_STR);
        $statement->bindValue(':type', $type, PDO::PARAM_STR);

        if ($statement->execute()) {
            $response = [
                'status' => 'success',
                'gameId' => $newGameId
            ];
        } else {
            $response = [
                'status' => 'failure',
                'error' => $statement->errorInfo()
            ];
        }

        return $response;
    }

    private function trackNotificationAction($type, $optionId = 0) {
        $statement = $this->connection->prepare('
                    INSERT INTO ' . PdoConnection::MATCHED_TABLE_NAME . ' 
                    (userId, datetime, type, optionId, gameId) 
                    VALUES (:userId, :datetime, :type, :optionId, :gameId)
                ');
        $statement->bindValue(':userId', $this->userId, PDO::PARAM_INT);
        $statement->bindValue(':datetime', $this->dateTime, PDO::PARAM_STR);
        $statement->bindValue(':type', $type, PDO::PARAM_STR);
        $statement->bindValue(':optionId', $optionId, PDO::PARAM_INT);
        $statement->bindValue(':gameId', $this->gameId, PDO::PARAM_STR);

        if($statement->execute()) {
            return true;
        }

        return ['error' => $statement->errorInfo()];
    }

    public function joinGame(): array
    {
        // Get last notification datetime
        $statement = $this->connection->prepare('
                    SELECT id 
                    FROM ' . PdoConnection::NOTIFICATIONS_TABLE_NAME . ' 
                    WHERE userId = :userId 
                    AND gameId = :gameId
                    AND type = :type
                ');
        $statement->bindValue(':userId', $this->userId, PDO::PARAM_INT);
        $statement->bindValue(':gameId', $this->gameId, PDO::PARAM_STR);
        $statement->bindValue(':type', self::NOTIFICATION_JOIN, PDO::PARAM_STR);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        // If there's no current last notification record for the userId create one
        if (empty($result)) {
            $result = $this->trackNotificationAction(self::NOTIFICATION_JOIN);
        } // UPDATE JOINED TIME
        else {
            $statement = $this->connection->prepare('
                        UPDATE ' . PdoConnection::JOINED_TABLE_NAME . ' 
                        SET datetime = :datetime 
                        WHERE userId = :userId
                        AND gameId = :gameId
                        AND type = :type
                    ');
            $statement->bindValue(':datetime', $this->dateTime, PDO::PARAM_STR);
            $statement->bindValue(':userId', $this->userId, PDO::PARAM_INT);
            $statement->bindValue(':gameId', $this->gameId, PDO::PARAM_STR);
            $statement->bindValue(':type', self::NOTIFICATION_JOIN, PDO::PARAM_STR);
            if(!$statement->execute()) {
                $result = ['error' => $statement->errorInfo()];
            }
        }

        if (empty($result['error'])) {
            $response = [
                'status' => 'success',
                'gameId' => $this->gameId
            ];
        } else {
            $response = [
                'status' => 'failure',
                'error' => $result['error']
            ];
        }

        return $response;
    }

    public function trackMatch(array $request): array
    {
        $optionId = (int) $request['optionId'];
        $rarity = constant('Bingo\Src\Services\Option::' . strtoupper(trim($request['rarity'])));
        $bingoed = (bool) $request['bingoed'];
        $coordinate = $request['coordinate'];

        $statement = $this->connection->prepare('
                    INSERT INTO ' . PdoConnection::MATCHED_TABLE_NAME . ' 
                    (userId, datetime, type, optionId, gameId, coordinates) 
                    VALUES (:userId, :datetime, :type, :optionId, :gameId, :coordinates)
                ');
        $statement->bindValue(':userId', $this->userId, PDO::PARAM_INT);
        $statement->bindValue(':datetime', $this->dateTime, PDO::PARAM_STR);
        $statement->bindValue(':type', self::NOTIFICATION_MATCH, PDO::PARAM_STR);
        $statement->bindValue(':optionId', $optionId, PDO::PARAM_INT);
        $statement->bindValue(':gameId', $this->gameId, PDO::PARAM_STR);
        $statement->bindValue(':coordinates', $coordinate, PDO::PARAM_STR);

        if(!$statement->execute()) {
            $result = ['error' => $statement->errorInfo()];
        }

        if(!empty($result['error'])) {
            return ['status' => 'failure', 'error' => $result['error']];
        }

        if($bingoed && empty($result['error'])) {
            $result = $this->trackNotificationAction(self::NOTIFICATION_BINGOED, $optionId);
        }

        if(!empty($result['error'])) {
            return ['status' => 'failure', 'error' => $result['error']];
        }

        // Add XP for matching a square
        $xpAmount = User::XP_AMOUNT[$rarity];

        if ($bingoed) {
            $xpAmount += User::XP_AMOUNT['bingo'];
        }

        $statement = $this->connection->prepare('
            UPDATE ' . PdoConnection::USERS_TABLE_NAME . ' 
            SET xp = xp + :xp
            WHERE id = :userId
        ');
        $statement->bindValue(':userId', $this->userId, PDO::PARAM_INT);
        $statement->bindValue(':xp', $xpAmount, PDO::PARAM_INT);

        if ($statement->execute()) {
            // Trigger updating the XP so it can be returned in the response
            $this->user->updateXp();
        } else {
            $errors[] = $statement->errorInfo();
        }

        // Track the option being matched so it can be used to figure out chance of matching in future
        $statTracking = $this->connection->prepare('
            UPDATE ' . PdoConnection::OPTIONS_TABLE . '
            SET matched = matched + 1
            WHERE id = :optionId
        ');
        $statTracking->bindValue(':optionId', $optionId, PDO::PARAM_INT);
        $statTracking->execute();

        if (empty($errors)) {
            $response = ['status' => 'success', 'xp' => $this->user->getXp(), 'maxXp' => $this->user->getLevelMax()];
        } else {
            $response = ['status' => 'failure', 'error' => $errors];
        }

        return $response;
    }

    public function trackLike(array $request): array
    {
        $optionId = $request['optionId'];

        $result = $this->trackNotificationAction(self::NOTIFICATION_LIKE, $optionId);

        if (empty($result['error'])) {
            $response = ['status' => 'success'];
        } else {
            $response = ['status' => 'failure', 'error' => $result['error']];
        }

        return $response;
    }

    public function trackDislike(array $request): array
    {
        $optionId = $request['optionId'];

        $result = $this->trackNotificationAction(self::NOTIFICATION_DISLIKE, $optionId);

        if (empty($result['error'])) {
            $response = ['status' => 'success'];
        } else {
            $response = ['status' => 'failure', 'error' => $result['error']];
        }

        return $response;
    }

    public function trackRefresh(array $request): array
    {
        $optionId = $request['optionId'];

        $result = $this->trackNotificationAction(self::NOTIFICATION_REFRESH, $optionId);

        if (empty($result['error'])) {
            $response = ['status' => 'success'];
        } else {
            $response = ['status' => 'failure', 'error' => $result['error']];
        }

        return $response;
    }

    public function checkNotification(array $request): array
    {
        $resetLastNotification = $request['resetLastNotification'] === 'true';
        $errors = [];

        // Get last notification datetime
        $statement = $this->connection->prepare('
                    SELECT datetime
                    FROM ' . PdoConnection::LAST_NOTIFICATION_TABLE_NAME . ' 
                    WHERE userId = :userId AND gameId = :gameId
                ');
        $statement->bindValue(':userId', $this->userId, PDO::PARAM_INT);
        $statement->bindValue(':gameId', $this->gameId, PDO::PARAM_STR);
        $statement->execute();
        $result = $statement->fetch(PDO::FETCH_ASSOC);

        // If there's no current last notification record for the userId create one
        if (empty($result)) {
            $statement = $this->connection->prepare('
                        INSERT INTO ' . PdoConnection::LAST_NOTIFICATION_TABLE_NAME . ' 
                        (userId, datetime, gameId) 
                        VALUES (:userId, :datetime, :gameId)
                    ');
            $statement->bindValue(':userId', $this->userId, PDO::PARAM_INT);
            $statement->bindValue(':datetime', $this->dateTime, PDO::PARAM_STR);
            $statement->bindValue(':gameId', $this->gameId, PDO::PARAM_STR);
            $statement->execute();

            $lastNotification = $this->dateTime;
        } else {
            // Reset the notification time if needed to make sure notifications are only shown for active sessions
            $lastNotification = ($resetLastNotification ? $this->dateTime : $result['datetime']);
        }

        // NOTIFICATIONS
        $notifications = [];

        $statement = $this->connection->prepare('
                    SELECT n.id, n.optionId, o.value, o.rarity, o.category, n.coordinates, n.datetime, n.type, u.id as userId, u.username
                    FROM ' . PdoConnection::NOTIFICATIONS_TABLE_NAME . ' AS n 
                    LEFT JOIN ' . PdoConnection::USERS_TABLE_NAME . ' AS u ON n.userId = u.id  
                    LEFT JOIN ' . PdoConnection::OPTIONS_TABLE . ' AS o ON n.optionId = o.id  
                    WHERE n.gameId = :gameId
                    ORDER BY n.datetime
                ');
        $statement->bindValue(':gameId', $this->gameId, PDO::PARAM_STR);

        if ($statement->execute()) {
            $notifications = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($notifications as $notification => $values) {
                if(in_array($values['type'], [self::NOTIFICATION_MATCH, self::NOTIFICATION_LIKE, self::NOTIFICATION_DISLIKE, self::NOTIFICATION_REFRESH])) {
                    $option = new Option(
                        $values['optionId'],
                        $values['rarity'],
                        $values['value'],
                        $values['category']
                    );

                    $notifications[$notification]['value'] = $option->getValue();
                    $notifications[$notification]['rarity'] = $option->getRarityString();
                }
            }
        } else {
            $errors['allNotifications'] = $statement->errorInfo();
        }

        // OPPONENT MATCHES
        $opponentMatches = [];

        $statement = $this->connection->prepare('
                    SELECT n.optionId, o.rarity, u.id, u.username
                    FROM ' . PdoConnection::NOTIFICATIONS_TABLE_NAME . ' AS n 
                    LEFT JOIN ' . PdoConnection::USERS_TABLE_NAME . ' AS u ON n.userId = u.id  
                    LEFT JOIN ' . PdoConnection::OPTIONS_TABLE . ' AS o ON n.optionId = o.id  
                    WHERE n.userId != :userId
                    AND n.gameId = :gameId
                    AND n.type = :type
                    ORDER BY n.datetime
                ');
        $statement->bindValue(':userId', $this->userId, PDO::PARAM_INT);
        $statement->bindValue(':gameId', $this->gameId, PDO::PARAM_STR);
        $statement->bindValue(':type', self::NOTIFICATION_MATCH, PDO::PARAM_STR);

        if ($statement->execute()) {
            $opponentMatches = $statement->fetchAll(PDO::FETCH_ASSOC);
            foreach ($opponentMatches as $match => $values) {
                $opponentMatches[$match]['rarity'] = Option::RARITY_MAPPER[$values['rarity']];
            }
        } else {
            $errors['opponentMatches'] = $statement->errorInfo();
        }

        if(!empty($errors)) {
            return [
                'status' => 'failure',
                'errors' => $errors
            ];
        }

        // Update the last notification timestamp
        $statement = $this->connection->prepare('
                    UPDATE ' . PdoConnection::LAST_NOTIFICATION_TABLE_NAME . ' 
                    SET datetime = :datetime 
                    WHERE userId = :userId AND gameId = :gameId
                ');
        $statement->bindValue(':datetime', $this->dateTime, PDO::PARAM_STR);
        $statement->bindValue(':userId', $this->userId, PDO::PARAM_INT);
        $statement->bindValue(':gameId', $this->gameId, PDO::PARAM_STR);
        $statement->execute();

        if (!empty($notifications) || !empty($opponentMatches)) {
            // output results
            $response = [
                'status' => 'success',
                'notifications' => $notifications,
                'opponentMatches' => $opponentMatches,
                'lastNotification' => $lastNotification
            ];
        } else {
            $response = ['status' => 'success'];
        }

        return $response;
    }

    public function trackCookieHide(): array
    {
        setcookie('noCookie', "1", time() + 60 * 60 * 24 * 90, '/bingo');

        return ['status' => 'success'];
    }

    public function changeType(array $request): array
    {
        $type = $request['type'] ?: NULL;

        if(isset($type)) {
            setcookie('type', $type, time() + 60 * 60 * 24 * 30, '/bingo');
        }
        setcookie('settingsSaved', "1", 0, '/bingo');

        return ['status' => 'success'];
    }

    public function getNewSquare(array $request): array
    {
        $type = $request['type'] ?? NULL;
        $rarity = constant('Bingo\Src\Services\Option::' . strtoupper(trim($request['rarity'])));
        $exclude = $request['existingSquares'];
        $costsXp = $request['costsXp'];
        $squaresToGet = $request['squaresToGet'];

        $bingoOptions = new BingoOptions($this->connection);

        $deadOptions = $bingoOptions->getOptionsByRarity(Option::DEAD, $type, $exclude);
        $rarityOptions = $bingoOptions->getOptionsByRarity($rarity, $type, $exclude);

        // If you've refreshed a square, add a possibility of getting a dead square
        if($costsXp) {
            $optionsArray = array_merge($deadOptions, $rarityOptions);
        } else {
            $optionsArray = $rarityOptions;
        }

        $randomOptions = BingoHelper::getRandomValueFromArray($optionsArray, $squaresToGet);

        if(empty($randomOptions)) {
            return [
                'status' => 'success',
                'message' => 'No new options to return'
            ];
        }

        $newOptions = [];

        foreach($randomOptions as $option) {
            $newOptions[] = (new Option(
                $option['id'],
                $option['rarity'],
                $option['value'],
                $option['category'],
                $option['gif'],
                $option['shown'],
                $option['matched']
            ))->toArray();
        }

        $this->trackShown($randomOptions);

        if($costsXp) {
            $xpAmount = User::XP_AMOUNT[$rarity] * 2;

            $statement = $this->connection->prepare('
                UPDATE ' . PdoConnection::USERS_TABLE_NAME . ' 
                SET xp = IF(xp - :xp > 0, xp - :xp, 0)
                WHERE id = :userId
            ');
            $statement->bindValue(':userId', $this->userId, PDO::PARAM_INT);
            $statement->bindValue(':xp', $xpAmount, PDO::PARAM_INT);

            if ($statement->execute()) {
                // Trigger updating the XP so it can be returned in the response
                $this->user->updateXp();
            } else {
                $errors[] = $statement->errorInfo();
            }
        }

        if(empty($errors)) {
            $response = [
                'status' => 'success',
                'option' => $newOptions,
                'xp' => $this->user->getXp(),
                'maxXp' => $this->user->getLevelMax()
            ];
        }
        else {
            $response = [
                'status' => 'failure',
                'errors' => $errors
            ];
        }

        return $response;
    }

    public function trackShown(array $options): void
    {
        $trackShownParams = [];
        $trackShownIds = [];

        foreach($options as $option) {
            $trackShownParams[] = ':shown' . $option['id'];
            $trackShownIds[] = $option['id'];
        }

        if(!empty($trackShownParams)) {
            $optionShown = $this->connection->prepare('
                UPDATE ' . PdoConnection::OPTIONS_TABLE . '
                SET shown = shown + 1
                WHERE id IN (' . implode(', ', $trackShownParams) . ')
            ');

            foreach ($trackShownIds as $index => $optionId) {
                $optionShown->bindValue($trackShownParams[$index], $optionId, PDO::PARAM_INT);
            }

            $optionShown->execute();
        }
    }
}