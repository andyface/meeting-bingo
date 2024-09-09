<?php
declare(strict_types=1);

namespace Bingo\Src\Services;

use Exception;

class Option
{
    const FREE = 1;
    const DEAD = 2;
    const COMMON = 3;
    const RARE = 4;
    const LEGENDARY = 5;

    const RARITY_VALIDATION = [
        self::FREE,
        self::DEAD,
        self::COMMON,
        self::RARE,
        self::LEGENDARY
    ];

    const RARITY_MAPPER = [
        self::FREE => 'free',
        self::DEAD => 'dead',
        self::COMMON => 'common',
        self::RARE => 'rare',
        self::LEGENDARY => 'legendary'
    ];

    private int $id;

    private int $rarity;

    private string $rarityString;

    private string $value;

    private ?string $category = null;

    private ?string $gif = null;

    private int $timesShown = 0;

    private int $timesMatched = 0;

    public function __construct(int $id, int $rarity, string $value, ?string $category = null, ?string $gif = null, int $shown = 0, int $matched = 0)
    {
        if(!in_array($rarity, self::RARITY_VALIDATION)) {
            throw new Exception('Rarity is not valid. Please use a valid rarity value');
        }

        $this->setId($id);
        $this->setRarity($rarity);
        $this->setCategory($category);
        $this->setValue($value);
        $this->setGif($gif);
        $this->setTimesShown($shown);
        $this->setTimesMatched($matched);
    }

    public function getRarity(): int
    {
        return $this->rarity;
    }

    public function getRarityString(): string
    {
        return self::RARITY_MAPPER[$this->rarity];
    }

    public function setRarity(int $rarity): void
    {
        $this->rarity = $rarity;
        $this->rarityString = $this->getRarityString();
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        if($this->getCategory() === null) {
            $this->value = $value;
        }
        else {
            $this->value = $this->getCategory() . ': ' . $value;
        }
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): void
    {
        $this->category = $category;
    }

    public function getGif(): ?string
    {
        return $this->gif;
    }

    public function setGif(?string $value): void
    {
        $this->gif = $value;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getTimesShown(): int
    {
        return $this->timesShown;
    }

    public function setTimesShown(int $timesShown): void
    {
        $this->timesShown = $timesShown;
    }

    public function getTimesMatched(): int
    {
        return $this->timesMatched;
    }

    public function setTimesMatched(int $timesMatched): void
    {
        $this->timesMatched = $timesMatched;
    }

    public function getMatchedPercentage(): float
    {
        if($this->getTimesShown() === 0) {
            return 0;
        }

        return ($this->getTimesMatched() / $this->getTimesShown()) * 100;
    }

    private function getRandomArrayKey(array $array): int
    {
        return array_rand($array, 1);
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}