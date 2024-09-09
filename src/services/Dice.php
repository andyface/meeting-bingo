<?php
declare(strict_types=1);

namespace Bingo\Src\Services;

class Dice
{
    const COIN = 2;
    const WEIGTHED_D3 = [0, 0, 1];
    const D6 = 6;
    const D20 = 20;
    const D30 = 30;

    const HEADS = 1;
    const TAILS = 2;

    private $sides;

    private $roll;

    /**
     * @var bool
     */
    private $debug;

    /**
     * Dice constructor.
     * @param $sides int|array Can accept either an integer or an array of values to use for the dice
     * @param bool $debug
     */
    public function __construct($sides, bool $debug = false)
    {
        $this->sides = $sides;
        $this->debug = $debug;
    }

    public function roll()
    {
        if($this->debug) {
            $this->roll = $this->getMaxValue();
            return $this;
        }

        if (is_array($this->sides)) {
            $this->roll = $this->sides[array_rand($this->sides, 1)];
        } else {
            $this->roll = random_int(1, $this->sides);
        }

        return $this;
    }

    public function getMaxValue()
    {
        if (is_array($this->sides)) {
            return max($this->sides);
        }

        return $this->sides;
    }

    public function getMinValue()
    {
        return 1;
    }

    public function getLastRoll()
    {
        return $this->roll;
    }

    /**
     * Alias for getLastRoll just to make a coin flip more fun
     * @return mixed
     */
    public function getLastFlip()
    {
        return $this->getLastRoll();
    }

    /**
     * Alias for roll just to make a coin flip more fun
     * @return $this
     */
    public function flip()
    {
        return $this->roll();
    }

    public function isHeads()
    {
        return $this->roll === self::HEADS;
    }

    public function isTails()
    {
        return $this->roll === self::TAILS;
    }
}