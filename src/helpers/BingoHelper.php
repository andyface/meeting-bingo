<?php
declare(strict_types=1);

namespace Bingo\Src\Helpers;

use Bingo\Src\Services\Option;

class BingoHelper
{
    /**
     * @param array $array Array to get random values from
     * @param int $numberToReturn Number of values to return
     * @param bool $returnKeys If an array should be returned if only one value is required
     * @return array|mixed
     */
    public static function getRandomValueFromArray(array &$array, $numberToReturn = 1, $returnKeys = true)
    {
        $keys = array_keys($array);
        shuffle($keys);

        $results = [];
        for ($i = 0; $i < $numberToReturn; $i++) {
            $results[$i] = $array[$keys[$i]];
            unset($array[$keys[$i]]);
        }

        // If there's only one result and return keys is false, return the value directly
        if (!$returnKeys && $numberToReturn === 1) {
            return $results[0];
        } else {
            return $results;
        }
    }

    public static function getEasiestMatchFromArray(array &$array, $numberToReturn = 1, $returnKeys = true)
    {
        usort($array, static function (Option $a, Option $b) {
            $aMatchedPercentage = $a->getMatchedPercentage();
            $bMatchedPercentage = $b->getMatchedPercentage();

            if ($aMatchedPercentage === $bMatchedPercentage) {
                return 0;
            }

            return ($aMatchedPercentage > $bMatchedPercentage) ? -1 : 1;
        });

        $results = [];
        for ($i = 0; $i < $numberToReturn; $i++) {
            $results[$i] = $array[$i];
            unset($array[$i]);
        }

        // If there's only one result and return keys is false, return the value directly
        if (!$returnKeys && $numberToReturn === 1) {
            return $results[0];
        } else {
            return $results;
        }
    }

    /**
     * Filters the options array and returns only one option per category
     * @param $optionsArray
     */
    public static function removeDuplicateCategoryOptions($optionsArray)
    {// Remove options from the array which have a duplicate category
        $existingCategories = [];
        shuffle($optionsArray);
        $filteredArray = array_filter($optionsArray, static function ($value) use (&$existingCategories) {
            $category = $value['category'];

            if ($category === null) {
                return true;
            }

            if (!in_array($category, $existingCategories, false)) {
                $existingCategories[] = $category;
                return true;
            }

            return false;
        });

        return $filteredArray;
    }

    public static function getPercentageValue($max, $value)
    {
        return round(($value / $max) * 100);
    }
}