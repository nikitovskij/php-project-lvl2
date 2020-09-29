<?php

namespace App\Parsers;

use Symfony\Component\Yaml\Yaml;

function parseYml(string $data): array
{
    return (array) Yaml::parse($data);
}