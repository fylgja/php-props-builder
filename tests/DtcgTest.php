<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder\Tests;

use Fylgja\PropsBuilder\Formats\Color;
use Fylgja\PropsBuilder\Json;
use Fylgja\PropsBuilder\Utils;
use PHPUnit\Framework\TestCase;

final class DtcgTest extends TestCase
{
    /**
     * Imports Design Tokens from JSON, the way they are read from a file.
     *
     * @param string $json
     * @return array<string|int, mixed>
     */
    private static function importTokens(string $json): array
    {
        return Fixtures::buildJson(json_decode($json), 'props.json', inputTypeTokens: true);
    }

    /**
     * @param mixed $color
     * @return mixed
     */
    private static function decodeColor(mixed $color): mixed
    {
        return $color === null ? null : json_decode(Json::encode($color), true);
    }

    public function testConvertsEveryColorSpace(): void
    {
        $this->assertSame(
            [
                'hex' => '#ff00ff',
                'srgb' => 'color(srgb 1 0 0.5 / 0.5)',
                'hsl' => 'hsl(none 0% 100%)',
                'hwb' => 'hwb(330 0% 0%)',
                'oklch' => 'oklch(0.7 0.1 200)',
                'p3' => 'color(display-p3 1 0 0)',
            ],
            self::importTokens('{
                "hex": { "$type": "color", "$value": { "colorSpace": "srgb", "components": [1, 0, 1], "hex": "#ff00ff" } },
                "srgb": { "$type": "color", "$value": { "colorSpace": "srgb", "components": [1, 0, 0.5], "alpha": 0.5, "hex": "#ff0080" } },
                "hsl": { "$type": "color", "$value": { "colorSpace": "hsl", "components": ["none", 0, 100] } },
                "hwb": { "$type": "color", "$value": { "colorSpace": "hwb", "components": [330, 0, 0] } },
                "oklch": { "$type": "color", "$value": { "colorSpace": "oklch", "components": [0.7, 0.1, 200] } },
                "p3": { "$type": "color", "$value": { "colorSpace": "display-p3", "components": [1, 0, 0] } }
            }'),
        );
    }

    public function testConvertsSimpleTypes(): void
    {
        $this->assertSame(
            [
                'size' => '0.5rem',
                'duration' => '200ms',
                'ease' => 'cubic-bezier(0.5, 0, 1, 1)',
                'family' => '"Helvetica Neue", sans-serif',
                'familySingle' => '"Roboto Slab"',
                'weight' => 600,
            ],
            self::importTokens('{
                "size": { "$type": "dimension", "$value": { "value": 0.5, "unit": "rem" } },
                "duration": { "$type": "duration", "$value": { "value": 200, "unit": "ms" } },
                "ease": { "$type": "cubicBezier", "$value": [0.5, 0, 1, 1] },
                "family": { "$type": "fontFamily", "$value": ["Helvetica Neue", "sans-serif"] },
                "familySingle": { "$type": "fontFamily", "$value": "Roboto Slab" },
                "weight": { "$type": "fontWeight", "$value": "semi-bold" }
            }'),
        );
    }

    public function testConvertsCompositeTokensIntoShorthands(): void
    {
        $black = '{ "colorSpace": "srgb", "components": [0, 0, 0], "hex": "#000000" }';

        $this->assertSame(
            [
                'border' => '1px solid var(--color-brand)',
                'transition' => '200ms cubic-bezier(0.5, 0, 1, 1) 0ms',
                'shadow' => '0px 1px 2px 0px #000000, inset 0px 1px 0px 0px #000000',
                'gradient' => '#000000 0%, var(--color-brand) calc(var(--position-end) * 100%)',
            ],
            self::importTokens('{
                "border": {
                    "$type": "border",
                    "$value": { "color": "{color.brand}", "width": { "value": 1, "unit": "px" }, "style": "solid" }
                },
                "transition": {
                    "$type": "transition",
                    "$value": {
                        "duration": { "value": 200, "unit": "ms" },
                        "delay": { "value": 0, "unit": "ms" },
                        "timingFunction": [0.5, 0, 1, 1]
                    }
                },
                "shadow": {
                    "$type": "shadow",
                    "$value": [
                        { "color": ' . $black . ', "offsetX": { "value": 0, "unit": "px" }, "offsetY": { "value": 1, "unit": "px" }, "blur": { "value": 2, "unit": "px" }, "spread": { "value": 0, "unit": "px" } },
                        { "color": ' . $black . ', "offsetX": { "value": 0, "unit": "px" }, "offsetY": { "value": 1, "unit": "px" }, "blur": { "value": 0, "unit": "px" }, "spread": { "value": 0, "unit": "px" }, "inset": true }
                    ]
                },
                "gradient": {
                    "$type": "gradient",
                    "$value": [
                        { "color": ' . $black . ', "position": 0 },
                        { "color": "{color.brand}", "position": "{position.end}" }
                    ]
                }
            }'),
        );
    }

    public function testSplitsTypographyIntoAPropertyPerPart(): void
    {
        $tokens = json_decode('{
            "heading": {
                "$type": "typography",
                "$value": {
                    "fontFamily": "{font.serif}",
                    "fontSize": { "value": 42, "unit": "px" },
                    "fontWeight": 700,
                    "letterSpacing": { "value": 0.1, "unit": "px" },
                    "lineHeight": 1.2
                }
            }
        }');

        $this->assertSame(
            ":where(:root) {\n"
                . "\t--heading-font-family: var(--font-serif);\n"
                . "\t--heading-font-size: 42px;\n"
                . "\t--heading-font-weight: 700;\n"
                . "\t--heading-letter-spacing: 0.1px;\n"
                . "\t--heading-line-height: 1.2;\n"
                . "}\n",
            Fixtures::build($tokens, 'tokens.css', inputTypeTokens: true),
        );
    }

    public function testInfersTheTypeFromTheValueShape(): void
    {
        $this->assertSame(
            ['color' => '#000000', 'size' => '4px'],
            self::importTokens('{
                "color": { "$value": { "colorSpace": "srgb", "components": [0, 0, 0], "hex": "#000000" } },
                "size": { "$value": { "value": 4, "unit": "px" } }
            }'),
        );
    }

    public function testKeepsLegacyStringValues(): void
    {
        $this->assertSame(
            ['size' => 'calc(var(--spacing) * 2)'],
            self::importTokens('{ "size": { "$type": "dimension", "$value": "calc({spacing} * 2)" } }'),
        );
    }

    public function testInheritsTypeFromParentGroups(): void
    {
        $this->assertSame(
            ['ease' => ['in' => 'cubic-bezier(0.5, 0, 1, 1)']],
            self::importTokens('{ "ease": { "$type": "cubicBezier", "in": { "$value": [0.5, 0, 1, 1] } } }'),
        );
    }

    public function testWritesRootTokensWithoutASuffix(): void
    {
        $tokens = json_decode('{
            "spacing": {
                "$type": "dimension",
                "$root": { "$value": { "value": 4, "unit": "px" } },
                "lg": { "$value": { "value": 8, "unit": "px" } }
            },
            "gap": { "$value": "{spacing.$root}" }
        }');

        $this->assertSame(
            ":where(:root) {\n\t--spacing: 4px;\n\t--spacing-lg: 8px;\n\t--gap: var(--spacing);\n}\n",
            Fixtures::build($tokens, 'tokens.css', inputTypeTokens: true),
        );
    }

    public function testDeepMergesExtendsGroups(): void
    {
        $this->assertEquals(
            [
                'button' => ['padding' => '4px', 'radius' => '2px'],
                'button-lg' => ['padding' => '8px', 'radius' => '2px'],
            ],
            self::importTokens('{
                "button": {
                    "$type": "dimension",
                    "padding": { "$value": { "value": 4, "unit": "px" } },
                    "radius": { "$value": { "value": 2, "unit": "px" } }
                },
                "button-lg": {
                    "$extends": "{button}",
                    "padding": { "$value": { "value": 8, "unit": "px" } }
                }
            }'),
        );
    }

    public function testThrowsOnCircularExtends(): void
    {
        $this->expectExceptionMessageMatches('/Circular "\$extends"/');

        self::importTokens('{ "a": { "$extends": "{b}" }, "b": { "$extends": "#/a" } }');
    }

    public function testResolvesRefs(): void
    {
        $this->assertSame(
            [
                'blue' => 'oklch(0.5 0.2 260)',
                'link' => 'var(--blue)',
                'sky' => 'oklch(0.8 0.1 260)',
            ],
            self::importTokens('{
                "blue": { "$type": "color", "$value": { "colorSpace": "oklch", "components": [0.5, 0.2, 260] } },
                "link": { "$type": "color", "$value": { "$ref": "#/blue/$value" } },
                "sky": {
                    "$type": "color",
                    "$value": { "colorSpace": "oklch", "components": [0.8, 0.1, { "$ref": "#/blue/$value/components/2" }] }
                }
            }'),
        );
    }

    public function testThrowsOnARefToAnotherFile(): void
    {
        $this->expectExceptionMessageMatches('/only references within the same document are supported/');

        self::importTokens('{ "link": { "$value": { "$ref": "colors.json#/blue" } } }');
    }

    public function testMergesResolverSetsAndTheDefaultModifierContext(): void
    {
        $resolver = json_decode('{
            "version": "2025.10",
            "name": "Test",
            "sets": {
                "base": { "sources": [{ "size": { "1": { "$type": "dimension", "$value": { "value": 4, "unit": "px" } } } }] }
            },
            "modifiers": {
                "theme": {
                    "contexts": {
                        "light": [{ "surface": { "$type": "color", "$value": { "colorSpace": "srgb", "components": [0, 0, 0], "hex": "#ffffff" } } }],
                        "dark": [{ "surface": { "$type": "color", "$value": { "colorSpace": "srgb", "components": [0, 0, 0], "hex": "#000000" } } }]
                    },
                    "default": "light"
                }
            },
            "resolutionOrder": [
                { "$ref": "#/sets/base" },
                { "type": "set", "name": "overrides", "sources": [{ "size": { "2": { "$type": "dimension", "$value": { "value": 8, "unit": "px" } } } }] },
                { "$ref": "#/modifiers/theme" }
            ]
        }');

        $this->assertSame(
            ":where(:root) {\n\t--size-1: 4px;\n\t--size-2: 8px;\n\t--surface: #ffffff;\n}\n",
            Fixtures::build($resolver, 'tokens.css', inputTypeTokens: true),
        );
    }

    public function testParsesCssColors(): void
    {
        $this->assertEquals(
            ['colorSpace' => 'srgb', 'components' => [1, 0, 0], 'alpha' => 0.533333333333, 'hex' => '#ff0000'],
            self::decodeColor(Color::parse('#f008')),
        );
        $this->assertEquals(
            ['colorSpace' => 'srgb', 'components' => [1, 0, 0], 'alpha' => 0.5, 'hex' => '#ff0000'],
            self::decodeColor(Color::parse('rgba(255, 0, 0, 0.5)')),
        );
        $this->assertEquals(
            ['colorSpace' => 'hsl', 'components' => [220, 40, 2], 'alpha' => 0.25],
            self::decodeColor(Color::parse('hsl(220 40% 2% / 25%)')),
        );
        $this->assertEquals(
            ['colorSpace' => 'oklch', 'components' => [0.7, 0.2, 200]],
            self::decodeColor(Color::parse('oklch(70% 50% 200deg)')),
        );
        $this->assertEquals(
            ['colorSpace' => 'display-p3', 'components' => [1, 0, 'none']],
            self::decodeColor(Color::parse('color(display-p3 1 0 none)')),
        );
    }

    public function testDoesNotParseColorsWithVarOrCalc(): void
    {
        $this->assertNull(Color::parse('oklch(99% calc(.03 * var(--chroma, 1)) var(--hue, 0))'));
        $this->assertNull(Color::parse('red'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function dtcgProps(): array
    {
        return [
            'spacing' => '0.25rem',
            'size' => [1 => 'calc(var(--spacing) * 1)', 'heading' => '36ch'],
            'gap' => 'var(--size-1)',
            'layer' => [1 => 1],
            'ratio' => ['widescreen' => '16/9'],
            'duration' => ['fast' => '150ms'],
            'ease' => [1 => 'cubic-bezier(0.25, 0.1, 0.1, 1)'],
            'font' => ['family' => "'Inter', system-ui, sans-serif", 'weight' => 700],
            'shadow-color' => [
                'DEFAULT' => 'hsl(0 0 0 / 10%)',
                '-@' => "@property --shadow-color { syntax: '<color>'; inherits: true; initial-value: black; }",
            ],
            'shadow' => [1 => '0 2px 1px -1px var(--shadow-color), inset 0 1px #000'],
            'shadow-color-@media:dark' => 'hsl(220 40% 2% / 25%)',
        ];
    }

    public function testWritesAResolverDocumentSkippingWhatTheFormatCantExpress(): void
    {
        $warnings = [];
        $resolver = Fixtures::buildJson(
            self::dtcgProps(),
            'fylgja.resolver.json',
            name: 'Fylgja',
            description: 'Fylgja tokens',
            onWarning: static function (string $message) use (&$warnings): void {
                $warnings[] = $message;
            },
        );

        $px = static fn (int $value): array => ['value' => $value, 'unit' => 'px'];
        $expectedTokens = [
            'spacing' => ['$type' => 'dimension', '$value' => ['value' => 0.25, 'unit' => 'rem']],
            'layer' => [1 => ['$type' => 'number', '$value' => 1]],
            'ratio' => ['widescreen' => ['$type' => 'number', '$value' => 16 / 9, '$description' => '16/9']],
            'duration' => ['fast' => ['$type' => 'duration', '$value' => ['value' => 150, 'unit' => 'ms']]],
            'ease' => [1 => ['$type' => 'cubicBezier', '$value' => [0.25, 0.1, 0.1, 1]]],
            'font' => [
                'family' => ['$type' => 'fontFamily', '$value' => ['Inter', 'system-ui', 'sans-serif']],
                'weight' => ['$type' => 'fontWeight', '$value' => 700],
            ],
            'shadow-color' => [
                '$root' => ['$type' => 'color', '$value' => ['colorSpace' => 'hsl', 'components' => [0, 0, 0], 'alpha' => 0.1]],
            ],
            'shadow' => [
                1 => [
                    '$type' => 'shadow',
                    '$value' => [
                        [
                            'color' => '{shadow-color.$root}',
                            'offsetX' => $px(0),
                            'offsetY' => $px(2),
                            'blur' => $px(1),
                            'spread' => $px(-1),
                        ],
                        [
                            'color' => ['colorSpace' => 'srgb', 'components' => [0, 0, 0], 'hex' => '#000000'],
                            'offsetX' => $px(0),
                            'offsetY' => $px(1),
                            'blur' => $px(0),
                            'spread' => $px(0),
                            'inset' => true,
                        ],
                    ],
                ],
            ],
        ];

        $this->assertEquals(
            [
                'version' => '2025.10',
                'name' => 'Fylgja',
                'description' => 'Fylgja tokens',
                'resolutionOrder' => [['type' => 'set', 'name' => 'Fylgja', 'sources' => [$expectedTokens]]],
            ],
            $resolver,
        );
        $this->assertCount(1, $warnings);
        $this->assertMatchesRegularExpression('/Skipped 3 token\(s\).*--size-1, --size-heading, --gap$/', $warnings[0]);
    }

    public function testRoundTripsAResolverDocumentToTheSameCss(): void
    {
        $props = self::dtcgProps();
        unset($props['size'], $props['gap']);

        $resolver = json_decode(Fixtures::build($props, 'tokens.resolver.json', onWarning: static fn () => null));
        $css = Fixtures::build($resolver, 'tokens.css', inputTypeTokens: true);

        $this->assertStringContainsString("\t--spacing: 0.25rem;", $css);
        $this->assertStringContainsString("\t--shadow-color: hsl(0 0% 0% / 0.1);", $css);
        $this->assertStringContainsString("\t--shadow-1: 0px 2px 1px -1px var(--shadow-color), inset 0px 1px 0px 0px #000000;", $css);
        $this->assertStringContainsString("\t--font-family: Inter, system-ui, sans-serif;", $css);
        $this->assertStringNotContainsString('prefers-color-scheme', $css);
    }

    public function testDetectsTheDtcgOutputFromTheExtension(): void
    {
        $this->assertSame('dtcg', Utils::fileType('fylgja.resolver.json'));
        $this->assertSame('tokens', Utils::fileType('fylgja.tokens.json'));
    }
}
