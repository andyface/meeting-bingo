<?php
declare(strict_types=1);

namespace Bingo\Src\Services;

use Bingo\Src\Controllers\BingoController;
use Bingo\Src\Helpers\BingoHelper;
use DateTime;
use DateTimeZone;
use PDO;

class Bingo
{
    const SQUARE_SCORES = [
        0 => 8,
        1 => 11,
        2 => 14,
        3 => 1,
        4 => 13,
        5 => 2,
        6 => 7,
        7 => 12,
        8 => 3,
        9 => 16,
        10 => 9,
        11 => 6,
        12 => 10,
        13 => 5,
        14 => 4,
        15 => 15
    ];

    const NUMBER_OF_SQUARES = 16;

    private PDO $connection;

    private $gameId;

    private $userId;

    private $bingoOptions;

    private $type = null;

    private $debug = false;

    public function __construct(PDO $connection, Game $game, User $user, bool $debug)
    {
        $this->connection = $connection;
        $this->gameId = $game->getGameId();
        $this->userId = $user->getId();
        $this->bingoOptions = new BingoOptions($connection);
        $this->type = $game->getType();
        $this->debug = $debug;
    }

    public function generateCard() : array
    {
        $bingoController = new BingoController($this->connection, $this->userId, $this->gameId);
        // setup the card array
        $card = [];

        // Current Datetime
        $dateTime = (new DateTime('@' . $_SERVER['REQUEST_TIME'], new DateTimeZone('+0000')))->format('Y-m-d H:i:s.v');

        $availableCommonOptions = $this->bingoOptions->getCommon($this->type);
        $availableRareOptions = $this->bingoOptions->getRare($this->type);
        $availableLegendaryOptions = $this->bingoOptions->getLegendary($this->type);

        // get a complete board of common options
        $commonOptions = BingoHelper::getRandomValueFromArray($availableCommonOptions, self::NUMBER_OF_SQUARES);
        // roll to see how many rare options to add based on a weighted D3
        $rareDice = (new Dice(Dice::WEIGTHED_D3, $this->debug))->roll();
        // Get the number of rare options based on the weighted roll
        $rareOptions = BingoHelper::getRandomValueFromArray($availableRareOptions, $rareDice->getLastRoll());

        // Add common options to bingo array
        foreach ($commonOptions as $key => $value) {
            $squares[$key]['id'] = $value->getId();
            $squares[$key]['value'] = $this->returnSquareValue($value);
            $squares[$key]['rarity'] = $value->getRarity();
            $squares[$key]['rarityString'] = $value->getRarityString();
            $squares[$key]['gif'] = $this->returnSquareGif($value);
        }

        // Override common options with any rare options
        if (!empty($rareOptions)) {
            foreach ($rareOptions as $key => $value) {
                $squares[$key]['id'] = $value->getId();
                $squares[$key]['value'] = $this->returnSquareValue($value);
                $squares[$key]['rarity'] = $value->getRarity();
                $squares[$key]['rarityString'] = $value->getRarityString();
                $squares[$key]['gif'] = $this->returnSquareGif($value);
            }
        }

        $legendaryDice = (new Dice(Dice::D30, $this->debug))->roll();
        $legendaryCoinFlip = false;

        // If a roll of 50 was made, add a legendary option to the card.
        if ($legendaryDice->getLastRoll() === $legendaryDice->getMaxValue()) {
            $legendaryCoinFlip = (new Dice(Dice::COIN))->flip();
            $legendaryKey = count($rareOptions);
            if ($legendaryCoinFlip->isHeads()) {
                // Gets a single legendary option
                $legendaryOptions = BingoHelper::getRandomValueFromArray($availableLegendaryOptions, 1);

                // Override common options with legendary option, positioned so it won't override rare option
                foreach ($legendaryOptions as $key => $value) {
                    $squares[$legendaryKey]['id'] = $value->getId();
                    $squares[$legendaryKey]['value'] = $this->returnSquareValue($value);
                    $squares[$legendaryKey]['rarity'] = $value->getRarity();
                    $squares[$legendaryKey]['rarityString'] = $value->getRarityString();
                    $squares[$legendaryKey]['gif'] = $this->returnSquareGif($value);
                }
            } else {
                // Gets four common options to use in an uber square
                $legendaryOptions = BingoHelper::getEasiestMatchFromArray($availableCommonOptions, 4);

                $squares[$legendaryKey] = [
                    'uberSquare' => true,
                    'rarity' => Option::LEGENDARY,
                    'rarityString' => Option::RARITY_MAPPER[Option::LEGENDARY],
                    'squares' => []
                ];

                // Override common options with legendary option, positioned so it won't override rare option
                foreach ($legendaryOptions as $key => $value) {
                    $squares[$legendaryKey]['squares'][$key]['id'] = $value->getId();
                    $squares[$legendaryKey]['squares'][$key]['value'] = $this->returnSquareValue($value);
                    $squares[$legendaryKey]['squares'][$key]['rarity'] = $value->getRarity();
                    $squares[$legendaryKey]['squares'][$key]['rarityString'] = $value->getRarityString();
                    $squares[$legendaryKey]['squares'][$key]['gif'] = $this->returnSquareGif($value);
                }
            }
        }

        // roll a D20 for dead and free spaces
        $unluckyDice = (new Dice(Dice::D20, $this->debug))->roll();
        $luckyDice = (new Dice(Dice::D20, $this->debug))->roll();

        // Replace the second from last option with a dead square if required
        if ($unluckyDice->getLastRoll() === $unluckyDice->getMaxValue()) {
            $availableDeadOptions = $this->bingoOptions->getDeadSpace($this->type);

            $deadSquare = BingoHelper::getRandomValueFromArray(
                $availableDeadOptions,
                1,
                false
            );
            $squares[14]['id'] = $deadSquare->getId();
            $squares[14]['value'] = $this->returnSquareValue($deadSquare);
            $squares[14]['rarity'] = $deadSquare->getRarity();
            $squares[14]['rarityString'] = $deadSquare->getRarityString();
            $squares[14]['gif'] = $this->returnSquareGif($deadSquare);
        }

        // Replace the last option with a free square if required
        if ($luckyDice->getLastRoll() === $luckyDice->getMaxValue()) {
            $availableFreeOptions = $this->bingoOptions->getFreeSquares($this->type);

            $freeSquare = BingoHelper::getRandomValueFromArray(
                $availableFreeOptions,
                1,
                false
            );
            $squares[15]['id'] = $freeSquare->getId();
            $squares[15]['value'] = $this->returnSquareValue($freeSquare);
            $squares[15]['rarity'] = $freeSquare->getRarity();
            $squares[15]['rarityString'] = $freeSquare->getRarityString();
            $squares[15]['gif'] = $this->returnSquareGif($freeSquare);
        }

        $bingoController->trackShown($squares);

        // mix up the values
        shuffle($squares);

        // Deal out the squares for each row
        $card['A'] = array_slice($squares, 0, 4, true);
        $card['B'] = array_slice($squares, 4, 4, true);
        $card['C'] = array_slice($squares, 8, 4, true);
        $card['D'] = array_slice($squares, 12, 4, true);

        // Save roll values for display
        $card['rolls']['unlucky'] = $unluckyDice->getLastRoll();
        $card['rolls']['lucky'] = $luckyDice->getLastRoll();
        $card['rolls']['rare'] = $rareDice->getLastRoll();
        $card['rolls']['legendary'] = $legendaryDice->getLastRoll();
        $card['rolls']['legendaryCoinFlip'] = $legendaryCoinFlip ? $legendaryCoinFlip->isTails() : false;

        $jsonCard = json_encode($card);

        /** @var PDO $statement */
        $statement = $this->connection->prepare('INSERT INTO ' . PdoConnection::CARDS_TABLE_NAME . ' (card, datetime, userId) VALUES (:card, :datetime, :userId)');
        $statement->bindParam(':card', $jsonCard, PDO::PARAM_STR);
        $statement->bindParam(':datetime', $dateTime, PDO::PARAM_STR);
        $statement->bindParam(':userId', $this->userId, PDO::PARAM_INT);
        $statement->execute();

        return $card;
    }

    /**
     * @param $option
     * @return string
     */
    private function returnSquareValue(Option $option)
    {
        if(is_array($option->getValue())){
            $randomValue = BingoHelper::getRandomValueFromArray($option->getValue(), 1, false);
            $squaresValue = $option->getCategory() . ': ' . $randomValue;
        }
        else {
            $squaresValue = $option->getValue();
        }

        return $squaresValue;
    }

    /**
     * @param $option
     * @return string
     */
    private function returnSquareGif(Option $option)
    {
        if(is_array($option->getGif())){
            $squaresGif = BingoHelper::getRandomValueFromArray($option->getGif(), 1, false);
        }
        else {
            $squaresGif = $option->getGif();
        }

        return $squaresGif;
    }
}