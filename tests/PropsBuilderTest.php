<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder\Tests;

use Fylgja\PropsBuilder\PropsBuilder;
use PHPUnit\Framework\TestCase;

final class PropsBuilderTest extends TestCase
{
    private const SHADOW_1 = '0 2px 1px -1px ' . Fixtures::SHADOW_UMBRA
        . ', 0 1px 1px ' . Fixtures::SHADOW_PENUMBRA
        . ', 0 1px 3px ' . Fixtures::SHADOW_AMBIENT;

    private const SHADOW_1_TOKENIZED = '0 2px 1px -1px hsl({shadow.color.value} / calc({shadow.weight.value} + 18%)),'
        . '0 1px 1px hsl({shadow.color.value} / calc({shadow.weight.value} + 12%)),'
        . '0 1px 3px hsl({shadow.color.value} / calc({shadow.weight.value} + 10%))';

    private const RED = [
        '#fff5f5', '#ffe3e3', '#ffc9c9', '#ffa8a8', '#ff8787',
        '#ff6b6b', '#fa5252', '#f03e3e', '#e03131', '#f00',
    ];

    /**
     * @param string $valueKey
     * @param array<string, string> $meta
     * @return array<string, array<string, mixed>>
     */
    private static function redTokens(string $valueKey, array $meta): array
    {
        $tokens = [];

        foreach (self::RED as $index => $hex) {
            $tokens["color-red-{$index}"] = [$valueKey => $hex, ...$meta];
        }

        return $tokens;
    }

    public function testCssOutput(): void
    {
        $css = Fixtures::build(Fixtures::props(), 'tokens.css');

        $this->assertStringStartsWith(":where(:root) {\n", $css);
        $this->assertStringContainsString("\t--layer-1: 1;", $css);
        $this->assertStringContainsString("\t--size-2: 0.5em;", $css);
        $this->assertStringContainsString("\t--color-red-0: #fff5f5;", $css);
        $this->assertStringContainsString("\t--color-green: #0f0;", $css);
        $this->assertStringContainsString("\t--border-size-2: 0.5em;", $css);
        $this->assertStringContainsString("\t--shadow-1: " . self::SHADOW_1 . ';', $css);
        $this->assertStringContainsString("\t--animate-spin: spin 1s linear infinite;", $css);
        $this->assertStringContainsString("\t--animate-rotate: rotate 1s linear infinite;", $css);
        $this->assertStringContainsString(
            "@media (prefers-color-scheme: dark) {\n\t:where(:root) {\n\t\t--shadow-color: hsl(220 40% 2%);\n\t\t--shadow-weight: 25%;\n\t}\n}",
            $css,
        );
        $this->assertStringContainsString('@keyframes spin { to { rotate: 360deg }}', $css);
        $this->assertStringContainsString('@keyframes rotate { to { rotate: 360deg }}', $css);
    }

    public function testScssOutput(): void
    {
        $scss = Fixtures::build(Fixtures::props(), 'tokens.scss');

        $this->assertStringStartsWith("\$layer-1: 1;\n", $scss);
        $this->assertStringContainsString('$color-red-0: #fff5f5;', $scss);
        $this->assertStringContainsString('$border-size-2: 0.5em;', $scss);
        $this->assertStringContainsString(
            '$shadow-1: "0 2px 1px -1px hsl(#{$shadow-color} / calc(#{$shadow-weight} + 18%)), '
                . '0 1px 1px hsl(#{$shadow-color} / calc(#{$shadow-weight} + 12%)), '
                . '0 1px 3px hsl(#{$shadow-color} / calc(#{$shadow-weight} + 10%))";',
            $scss,
        );
        $this->assertStringContainsString('$shadow-dark-color: hsl(220 40% 2%);', $scss);
        $this->assertStringContainsString('$shadow-dark-weight: 25%;', $scss);
        $this->assertStringContainsString('@keyframes spin { to { rotate: 360deg }}', $scss);
        $this->assertStringContainsString('@keyframes rotate { to { rotate: 360deg }}', $scss);
    }

    public function testDesignTokensJsonOutput(): void
    {
        $this->assertEquals(
            [
                'layer-1' => ['$value' => 1, '$type' => 'number'],
                'size-2' => ['$value' => '0.5em', '$type' => 'dimension'],
                'size-4' => ['$value' => '4em', '$type' => 'dimension'],
                ...self::redTokens('$value', ['$type' => 'color']),
                'color-green' => ['$value' => '#0f0', '$type' => 'color'],
                'color-blue' => ['$value' => '#00f', '$type' => 'color'],
                'border-size-2' => ['$value' => '0.5em', '$type' => 'dimension'],
                'border-size-4' => ['$value' => '4em', '$type' => 'dimension'],
                'shadow-1' => [
                    '$value' => [
                        '0 2px 1px -1px ' . Fixtures::SHADOW_UMBRA,
                        '0 1px 1px ' . Fixtures::SHADOW_PENUMBRA,
                        '0 1px 3px ' . Fixtures::SHADOW_AMBIENT,
                    ],
                ],
                'shadow-color' => ['$value' => 'gray', '$type' => 'color'],
                'shadow-weight' => ['$value' => '1%', '$type' => 'font-weight'],
                'shadow-@media:dark-color' => ['$value' => 'hsl(220 40% 2%)', '$type' => 'color'],
                'shadow-@media:dark-weight' => ['$value' => '25%', '$type' => 'font-weight'],
                'spin-@' => ['$value' => '@keyframes spin { to { rotate: 360deg }}'],
                'animateSpin' => ['$value' => 'spin 1s linear infinite'],
                'rotate-@' => ['$value' => '@keyframes rotate { to { rotate: 360deg }}'],
                'animateRotate' => ['$value' => 'rotate 1s linear infinite'],
            ],
            Fixtures::buildJson(Fixtures::props(), 'tokens.tokens.json'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function expectedFigmaTokens(): array
    {
        return [
            'other' => [
                'layer-1' => ['value' => 1, 'type' => 'other'],
                'spin-@' => ['value' => '@keyframes spin { to { rotate: 360deg }}', 'type' => 'other'],
                'animateSpin' => ['value' => 'spin 1s linear infinite', 'type' => 'other'],
                'rotate-@' => ['value' => '@keyframes rotate { to { rotate: 360deg }}', 'type' => 'other'],
                'animateRotate' => ['value' => 'rotate 1s linear infinite', 'type' => 'other'],
            ],
            'sizing' => [
                'size-2' => ['value' => '0.5em', 'type' => 'sizing'],
                'size-4' => ['value' => '4em', 'type' => 'sizing'],
            ],
            'color' => [
                ...self::redTokens('value', ['type' => 'color']),
                'color-green' => ['value' => '#0f0', 'type' => 'color'],
                'color-blue' => ['value' => '#00f', 'type' => 'color'],
            ],
            'borderWidth' => [
                'border-size-2' => ['value' => '0.5em', 'type' => 'borderWidth'],
                'border-size-4' => ['value' => '4em', 'type' => 'borderWidth'],
            ],
            'boxShadow' => [
                'shadow-1' => ['value' => self::SHADOW_1_TOKENIZED, 'type' => 'boxShadow'],
                'shadow-color' => ['value' => 'gray', 'type' => 'boxShadow'],
                'shadow-weight' => ['value' => '1%', 'type' => 'boxShadow'],
                'shadow-@media:dark-color' => ['value' => 'hsl(220 40% 2%)', 'type' => 'boxShadow'],
                'shadow-@media:dark-weight' => ['value' => '25%', 'type' => 'boxShadow'],
            ],
        ];
    }

    public function testFigmaTokensJsonOutput(): void
    {
        $this->assertEquals(
            self::expectedFigmaTokens(),
            Fixtures::buildJson(Fixtures::props(), 'tokens.figma-tokens.json'),
        );
    }

    public function testFigmaTokensNestUnderWrapper(): void
    {
        $this->assertEquals(
            ['fylgja' => self::expectedFigmaTokens()],
            Fixtures::buildJson(Fixtures::props(), 'tokens.figma-tokens.sync.json', wrapper: 'fylgja'),
        );
    }

    public function testStyleDictionaryJsonOutput(): void
    {
        $this->assertEquals(
            [
                'layer' => [1 => ['value' => 1]],
                'size' => [
                    'size-2' => ['value' => '0.5em', 'type' => 'size'],
                    'size-4' => ['value' => '4em', 'type' => 'size'],
                ],
                'color' => [
                    ...self::redTokens('value', ['type' => 'color']),
                    'color-green' => ['value' => '#0f0', 'type' => 'color'],
                    'color-blue' => ['value' => '#00f', 'type' => 'color'],
                ],
                'border' => [
                    'width' => [
                        'border-size-2' => ['value' => '0.5em', 'type' => 'border-width'],
                        'border-size-4' => ['value' => '4em', 'type' => 'border-width'],
                    ],
                ],
                'box' => [
                    'shadow' => [
                        'shadow-1' => ['value' => self::SHADOW_1_TOKENIZED, 'type' => 'box-shadow'],
                        'shadow-color' => ['value' => 'gray', 'type' => 'box-shadow'],
                        'shadow-weight' => ['value' => '1%', 'type' => 'box-shadow'],
                        'shadow-@media:dark-color' => ['value' => 'hsl(220 40% 2%)', 'type' => 'box-shadow'],
                        'shadow-@media:dark-weight' => ['value' => '25%', 'type' => 'box-shadow'],
                    ],
                ],
                'spin' => ['@' => ['value' => '@keyframes spin { to { rotate: 360deg }}']],
                'animateSpin' => ['value' => 'spin 1s linear infinite'],
                'rotate' => ['@' => ['value' => '@keyframes rotate { to { rotate: 360deg }}']],
                'animateRotate' => ['value' => 'rotate 1s linear infinite'],
            ],
            Fixtures::buildJson(Fixtures::props(), 'tokens.style-dictionary-tokens.json'),
        );
    }

    public function testPlainJsonOutputKeepsTheProps(): void
    {
        $json = Fixtures::build(Fixtures::props(), 'props.json');

        $this->assertSame(json_encode(Fixtures::props(), JSON_UNESCAPED_SLASHES), json_encode(json_decode($json), JSON_UNESCAPED_SLASHES));
        $this->assertStringContainsString("\"red\": {\n      \"0\": \"#fff5f5\"", $json, 'a scale passed as an object stays an object');
    }

    public function testCssJitOutput(): void
    {
        $module = Fixtures::build(Fixtures::props(), 'tokens.js', parseAs: 'css-jit');
        $this->assertStringStartsWith('export default ', $module);

        $jit = json_decode(substr($module, strlen('export default ')), true);
        $this->assertSame('#fff5f5', $jit['--color-red-0']);
        $this->assertSame('rotate 1s linear infinite', $jit['--animateRotate']);
        $this->assertSame('25%', $jit['--shadow-@media:dark-weight']);
        $this->assertCount(3, $jit['--shadow-1']);
        $this->assertCount(26, $jit);
    }

    public function testCustomSelector(): void
    {
        $css = Fixtures::build(Fixtures::props(), 'tokens.css', selector: '.fylgja-test-zone');

        $this->assertStringStartsWith(".fylgja-test-zone {\n", $css);
        $this->assertStringContainsString(
            "@media (prefers-color-scheme: dark) {\n\t.fylgja-test-zone {\n\t\t--shadow-color: hsl(220 40% 2%);",
            $css,
        );
    }

    public function testTailwindThemeSelector(): void
    {
        $css = Fixtures::build(Fixtures::props(), 'tokens.css', selector: '@theme');

        $this->assertStringStartsWith("@theme {\n", $css);
        $this->assertStringContainsString(
            "@keyframes spin { to { rotate: 360deg }}\n\n\t@keyframes rotate { to { rotate: 360deg }}\n}",
            $css,
            'keyframes should be nested inside the @theme block',
        );
        $this->assertStringContainsString(
            "@media (prefers-color-scheme: dark) {\n\t:root {\n\t\t--shadow-color: hsl(220 40% 2%);",
            $css,
            'dark mode props should stay scoped to :root',
        );
    }

    public function testKeyframeOnlyProps(): void
    {
        $this->assertSame(
            ":where(:root) {\n"
                . "\t--animate-spin: spin 1s linear infinite;\n"
                . "\t--animate-rotate: rotate 1s linear infinite;\n"
                . "}\n"
                . "\n@keyframes spin { to { rotate: 360deg }}"
                . "\n\n@keyframes rotate { to { rotate: 360deg }}\n",
            Fixtures::build(Fixtures::PROPS_KEYS, 'tokens.css'),
        );
        $this->assertSame(
            "\$animate-spin: spin 1s linear infinite;\n"
                . "\$animate-rotate: rotate 1s linear infinite;\n"
                . "\n@keyframes spin { to { rotate: 360deg }}"
                . "\n\n@keyframes rotate { to { rotate: 360deg }}\n",
            Fixtures::build(Fixtures::PROPS_KEYS, 'tokens.scss'),
        );
        $this->assertSame(Fixtures::PROPS_KEYS, Fixtures::buildJson(Fixtures::PROPS_KEYS, 'keys.json'));
    }

    public function testBannerStripPrefixAndRename(): void
    {
        $css = Fixtures::build(
            ['tokens' => ['values' => ['colors' => ['primary' => 'red']]]],
            'tokens.css',
            banner: '/* Generated */',
            stripPrefix: 'tokens.values',
            rename: ['colors' => 'color'],
        );

        $this->assertSame("/* Generated */\n:where(:root) {\n\t--color-primary: red;\n}\n", $css);
    }

    public function testWritesToFile(): void
    {
        $filename = __DIR__ . '/_write-test.css';
        $css = PropsBuilder::build(['gap' => '1rem'], $filename);

        $this->assertSame($css, file_get_contents($filename));
        unlink($filename);
    }

    public function testFigmaSyntaxDesignTokensFile(): void
    {
        $css = Fixtures::build(Fixtures::designTokens(), 'tokens.css', inputTypeTokens: true, inputTypeSyntax: 'figma');

        $this->assertStringContainsString('--layer-1: 1;', $css, 'a top-level `other` group should be unwrapped');
        $this->assertStringContainsString('--animate-spin: spin 1s linear infinite;', $css);
        $this->assertStringContainsString('--sizing-size-2: 0.5em;', $css);
        $this->assertStringContainsString('--color-color-red-0: #fff5f5;', $css);
        $this->assertStringContainsString('--border-width-border-size-2: 0.5em;', $css);
        $this->assertStringContainsString(
            '--box-shadow-shadow-1: 0 2px 1px -1px ' . Fixtures::SHADOW_UMBRA
                . ',0 1px 1px ' . Fixtures::SHADOW_PENUMBRA
                . ',0 1px 3px ' . Fixtures::SHADOW_AMBIENT . ';',
            $css,
        );
        $this->assertStringContainsString(
            "@media (prefers-color-scheme: dark) {\n\t:where(:root) {\n\t\t--box-shadow-shadow-color: hsl(220 40% 2%);\n\t\t--box-shadow-shadow-weight: 25%;\n\t}\n}",
            $css,
        );
        $this->assertStringContainsString('@keyframes spin { to { rotate: 360deg }}', $css);
        $this->assertStringNotContainsString('@keyframes rotate', $css);
        $this->assertStringNotContainsString('--size-2:', $css, 'the `sizing` group must not be stripped away');
        $this->assertStringNotContainsString('--border-size-2:', $css, 'the `borderWidth` group must not be stripped away');
        $this->assertStringNotContainsString('--shadow-1:', $css, 'the `boxShadow` group must not be stripped away');
    }

    public function testFigmaKeepsNestedOtherGroup(): void
    {
        $css = Fixtures::build(
            ['sizing' => ['other' => ['gap-1' => ['value' => '4px']]]],
            'tokens.css',
            inputTypeTokens: true,
            inputTypeSyntax: 'figma',
        );

        $this->assertStringContainsString('--sizing-other-gap-1: 4px;', $css);
    }

    public function testFigmaCollapsesRedundantColorGroups(): void
    {
        foreach (['color', 'colors'] as $nestedKey) {
            $css = Fixtures::build(
                ['colors' => [$nestedKey => ['primary' => ['value' => '#1d4ed8']]]],
                'tokens.css',
                inputTypeTokens: true,
                inputTypeSyntax: 'figma',
            );

            $this->assertStringContainsString('--colors-primary: #1d4ed8;', $css);
            $this->assertStringNotContainsString("--colors-{$nestedKey}-primary", $css);
        }
    }

    public function testStitchMarkdownExport(): void
    {
        $css = Fixtures::build(Fixtures::stitchMarkdown(), 'tokens.css', inputTypeTokens: true, inputTypeSyntax: 'stitch');

        $this->assertSame(
            ":where(:root) {\n"
                . "\t--typography-headline-lg-font-family: Titillium Web;\n"
                . "\t--typography-headline-lg-font-size: 24px;\n"
                . "\t--typography-headline-lg-font-weight: 700;\n"
                . "\t--spacing-gutter: 16px;\n"
                . "\t--color-primary: #a7000c;\n"
                . "\t--color-on-primary: #ffffff;\n"
                . "}\n",
            $css,
        );
    }
}
