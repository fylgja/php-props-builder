<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder\Tests;

use Fylgja\PropsBuilder\Formats\Stitch;
use PHPUnit\Framework\TestCase;

final class StitchTest extends TestCase
{
    public function testParsesTheFrontmatterGroups(): void
    {
        $markdown = implode("\n", [
            '---',
            'colors:',
            "  primary: '#a7000c'",
            'typography:',
            '  headline-lg:',
            '    fontFamily: Titillium Web',
            '    fontSize: 24px',
            'spacing:',
            '  gutter: 16px',
            '---',
            '',
            '## Brand & Style',
            '',
            'Prose describing the design system, not a token.',
        ]);

        $this->assertSame(
            [
                'typography' => ['headline-lg' => ['fontFamily' => 'Titillium Web', 'fontSize' => '24px']],
                'spacing' => ['gutter' => '16px'],
                'color' => ['primary' => '#a7000c'],
            ],
            Stitch::parse($markdown),
        );
    }

    public function testDropsTheMetadataKeys(): void
    {
        $markdown = implode("\n", [
            '---',
            'name: Technical Precision',
            'description: A design system export',
            'colors:',
            '  primary: red',
            '---',
        ]);

        $this->assertSame(['color' => ['primary' => 'red']], Stitch::parse($markdown));
    }

    public function testPassesThroughOtherGroups(): void
    {
        $markdown = implode("\n", [
            '---',
            'name: Hyvä Default System',
            'form:',
            "  bg: '#ffffff'",
            '  radius: var(--radius-lg)',
            'fontFamily:',
            '  sans: Inter',
            'rounded:',
            '  sm: 0.25rem',
            '  DEFAULT: 0.5rem',
            'spacing: 0.25rem',
            '---',
        ]);

        $this->assertSame(
            [
                'form' => ['bg' => '#ffffff', 'radius' => 'var(--radius-lg)'],
                'fontFamily' => ['sans' => 'Inter'],
                'rounded' => ['sm' => '0.25rem', 'DEFAULT' => '0.5rem'],
                'spacing' => '0.25rem',
            ],
            Stitch::parse($markdown),
        );
    }

    public function testIgnoresTheMarkdownBody(): void
    {
        $markdown = implode("\n", ['---', 'colors:', '  primary: red', '---', '', 'colors: this is prose, not YAML']);

        $this->assertSame(['color' => ['primary' => 'red']], Stitch::parse($markdown));
    }

    public function testReturnsAnEmptyMapWithoutFrontmatter(): void
    {
        $this->assertSame([], Stitch::parse("# Just a heading\n\nNo frontmatter here."));
    }

    public function testRenamesColorsToColor(): void
    {
        $this->assertSame(['color' => ['primary' => 'red']], Stitch::parse("---\ncolors:\n  primary: red\n---"));
        $this->assertSame(['color' => ['primary' => 'red']], Stitch::parse("---\ncolor:\n  primary: red\n---"));
    }

    public function testNeverOverwritesAnExistingColorGroup(): void
    {
        $this->assertSame(
            ['color' => ['primary' => 'blue']],
            Stitch::parse("---\ncolors:\n  primary: red\ncolor:\n  primary: blue\n---"),
        );
    }
}
