<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Fylgja\PropsBuilder\PropsBuilder;

$cases = json_decode((string) file_get_contents(__DIR__ . '/cases.json'), false, 512, JSON_THROW_ON_ERROR);

/**
 * Reads the input of a parity case, Markdown files are passed as a string.
 *
 * @param object $case
 * @return mixed
 */
function readInput(object $case): mixed
{
    if (!isset($case->inputFile)) {
        return $case->input;
    }

    $content = (string) file_get_contents(__DIR__ . '/' . $case->inputFile);

    return str_ends_with($case->inputFile, '.md') ? $content : json_decode($content, false, 512, JSON_THROW_ON_ERROR);
}

foreach ($cases as $case) {
    echo "### {$case->name}\n";
    echo PropsBuilder::build(
        readInput($case),
        $case->filename,
        ...json_decode(json_encode($case->options ?? []), true),
        writeToFile: false,
        onWarning: static function (string $message): void {
            echo "WARNING: {$message}\n";
        },
    ), "\n";
}
