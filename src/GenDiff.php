<?php

namespace App;

use Funct\Collection;
use App\Parsers\JsonParser;
use App\Parsers\YmlParser;

use function App\Renders\Pretty\render;

const FIRST_FILE = '<firstFile>';
const SECOND_FILE = '<secondFile>';

function run($args)
{
    $firstFile  = $args[FIRST_FILE];
    $secondFile = $args[SECOND_FILE];

    return checkDiff($firstFile, $secondFile);
}

function checkDiff($firstFile, $secondFile)
{
    $contentFirst = getFileContent($firstFile);
    $contentSecond = getFileContent($secondFile);

    $comparedTree = makeCompare($contentFirst, $contentSecond);
    return render($comparedTree);
}

function getFileContent($file)
{
    $filePath = realpath($file);
    if (!$filePath) {
        throw new \Exception('File does not found.');
    }

    $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);
    if (!isset($fileExtension)) {
        throw new \Exception('Unknown file extension.');
    }

    $fileHandler   = fileHandlers($fileExtension);
    $contentOfFile = file_get_contents($filePath);
    if (!$fileExtension) {
        throw new \Exception('Can not read the file.');
    }

    return $fileHandler($contentOfFile);
}

function fileHandlers($fileExtension)
{
    $handlers = [
        'json' => fn($data) => JsonParser\parseJson($data),
        'yml'  => fn($data) => YmlParser\parseYml($data),
        'yaml' => fn($data) => YmlParser\parseYml($data)
    ];

    return $handlers[$fileExtension];
}

function makeCompare(array $contentFirst, array $contentSecond)
{
    $listOfKeys = Collection\union(array_keys($contentFirst), array_keys($contentSecond));
    $sortedKeys = sortKeys($listOfKeys);

    $checkChildren = function ($key) use ($contentFirst, $contentSecond) {
        $firstChild  = $contentFirst[$key] ?? null;
        $secondChild = $contentSecond[$key] ?? null;

        if (is_array($firstChild) && is_array($secondChild)) {
            return [ 'key' => $key, 'state' => 'same', 'value' => makeCompare($firstChild, $secondChild)];
        }

        return getComparingData($key, $contentFirst, $contentSecond);
    };

    return array_values(array_map($checkChildren, $sortedKeys));
}

function getComparingData($key, $contentFirst, $contentSecond)
{
    $isChanged = isItemsChanged($key, $contentFirst, $contentSecond);
    $isAdded   = isItemAdded($key, $contentFirst, $contentSecond);
    $isDeleted = isItemDeleted($key, $contentFirst, $contentSecond);

    if ($isChanged) {
        return [
            'key' => $key, 'state' => 'change',
            'value' => [
                'before' => $contentFirst[$key],
                'after' => $contentSecond[$key]
                ]
            ];
    }

    if ($isAdded) {
        return ['key' => $key, 'state' => 'new', 'value' => $contentSecond[$key]];
    }

    if ($isDeleted) {
        return ['key' => $key, 'state' => 'delete', 'value' => $contentFirst[$key]];
    }

    return ['key' => $key, 'state' => 'same', 'value' => $contentFirst[$key]];
}

function isItemsSame($key, array $dataFirst, array $dataSecond)
{
    if (array_key_exists($key, $dataFirst)) {
        if (array_key_exists($key, $dataSecond)) {
            return $dataFirst[$key] === $dataSecond[$key];
        }
    }

    return false;
}

function isItemsChanged($key, array $dataFirst, array $dataSecond)
{
    if (array_key_exists($key, $dataFirst)) {
        if (array_key_exists($key, $dataSecond)) {
            return $dataFirst[$key] !== $dataSecond[$key];
        }
    }

    return false;
}

function isItemAdded($key, array $dataFirst, array $dataSecond)
{
    return !array_key_exists($key, $dataFirst) && array_key_exists($key, $dataSecond);
}

function isItemDeleted($key, array $dataFirst, array $dataSecond)
{
    return array_key_exists($key, $dataFirst) && !array_key_exists($key, $dataSecond);
}

function sortKeys($listOfKeys)
{
    usort($listOfKeys, fn($firstKey, $secondKey) => $firstKey <=> $secondKey);

    return $listOfKeys;
}
