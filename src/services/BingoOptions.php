<?php
declare(strict_types=1);

namespace Bingo\Src\Services;

use Bingo\Src\Helpers\BingoHelper;
use Exception;
use PDO;

class BingoOptions
{
    private $connection;

    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param string|null $type
     * @return array
     */
    public function getFreeSquares(?string $type): array
    {
        return $this->createOptionObjects($this->getOptionsByRarity(Option::FREE, $type));
    }

    /**
     * @return array
     */
    public function getDeadSpace(?string $type): array
    {
        return $this->createOptionObjects($this->getOptionsByRarity(Option::DEAD, $type));
    }

    /**
     * @param string|null $type
     * @return array
     */
    public function getLegendary(?string $type): array
    {
        return $this->createOptionObjects($this->getOptionsByRarity(Option::LEGENDARY, $type, [999999999]));
    }

    /**
     * @param string|null $type
     * @return array
     */
    public function getRare(?string $type): array
    {
        return $this->createOptionObjects($this->getOptionsByRarity(Option::RARE, $type));
    }

    /**
     * @param string|null $type
     * @return array
     */
    public function getCommon(?string $type): array
    {
        return $this->createOptionObjects($this->getOptionsByRarity(Option::COMMON, $type));
    }

    /**
     * @param int $rarity
     * @param string|null $type
     * @param array $exclude An array of ids to exclude from the search
     * @return array
     */
    public function getOptionsByRarity(int $rarity, ?string $type, array $exclude = []): array
    {
        $excludeSql = array_map(function ($value){
            return ':exclude' . $value;
        }, $exclude);

        $where = 'rarity = :rarity';

        if($type) {
            $where .= ' AND (type = :type OR type IS NULL)';
        }

       if(!empty($exclude)) {
            $where .= ' AND id NOT IN (' . implode(', ', $excludeSql) . ')';
        }

        $statement = $this->connection->prepare('
            SELECT id, `value`, category, rarity, gif, shown, matched
            FROM options 
            WHERE ' . $where
        );
        $statement->bindValue(':rarity', $rarity, PDO::PARAM_INT);
        if($type) {
            $statement->bindValue(':type', $type, PDO::PARAM_STR);
        }
        if(!empty($exclude)) {
            foreach($exclude as $value) {
                $value = (int) $value;
                $statement->bindValue(':exclude' . $value, $value, PDO::PARAM_INT);
            }
        }
        $statement->execute();

        return BingoHelper::removeDuplicateCategoryOptions($statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** Converts results from the db into objects */
    private function createOptionObjects($options) {
        $returnArray = [];

        foreach($options as $option) {
            try {
                $returnArray[] = new Option(
                    (int) $option['id'],
                    (int) $option['rarity'],
                    $option['value'],
                    $option['category'],
                    $option['gif'],
                    (int) $option['shown'],
                    (int) $option['matched']
                );
            }
            catch(Exception $exception) {
                //Oh no, an error occurred. let's ignore it.
            }
        }

        return $returnArray;
    }
}