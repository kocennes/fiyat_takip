<?php

namespace App\Utils;

/**
 * BISAVUNMA logo printed on the proforma PDF and Excel headers.
 *
 * The file is the brand logo the interface ships (public/brand). It is embedded as a data URI so
 * the PDF renderer never has to reach APP_URL, which is not always reachable from inside the
 * container.
 */
class BrandLogo
{
    private const PUBLIC_FILE = 'brand/bisavunma-logo.png';

    private static ?string $dataUri = null;

    /** Absolute path of the logo file, or null when the interface assets are not published. */
    public static function path(): ?string
    {
        $path = public_path(self::PUBLIC_FILE);

        return is_file($path) ? $path : null;
    }

    /** The logo as a data URI, falling back to its public URL. */
    public static function dataUri(): string
    {
        if (self::$dataUri !== null) {
            return self::$dataUri;
        }

        $path = self::path();
        $bytes = $path ? @file_get_contents($path) : false;

        return self::$dataUri = $bytes !== false
            ? 'data:image/png;base64,'.base64_encode($bytes)
            : asset(self::PUBLIC_FILE);
    }
}
