<?php

// Fylgja (https://fylgja.dev)
// Licensed under MIT Open Source

declare(strict_types=1);

namespace Fylgja\PropsBuilder;

final class ToCssJit
{
    /**
     * Creates a flat JSON object of `--` prefixed CSS props, for JIT tools like jit-props.
     *
     * @param array<string|int, mixed> $props
     * @return string
     */
    public static function build(array $props): string
    {
        $tokens = [];

        foreach (Utils::flatten($props) as $key => $value) {
            $tokens["--{$key}"] = $value;
        }

        return Json::encode($tokens);
    }
}
