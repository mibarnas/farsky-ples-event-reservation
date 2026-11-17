<?php

namespace App\Support;

/**
 * The version of the code that is currently running, shown in the footer.
 *
 * On the server the deploy workflow writes version.json into the app root
 * (commit, build number, deploy time). Without that file (local development)
 * it falls back to the commit checked out in .git.
 */
class AppVersion
{
    private static ?array $cached = null;

    /**
     * @return array{commit: ?string, build: ?string, deployed_at: ?string}
     */
    public static function get(): array
    {
        return self::$cached ??= self::fromVersionFile() ?? self::fromGit();
    }

    private static function fromVersionFile(): ?array
    {
        $path = base_path('version.json');
        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            return null;
        }

        return [
            'commit' => self::stringOrNull($data['commit'] ?? null),
            'build' => self::stringOrNull($data['build'] ?? null),
            'deployed_at' => self::stringOrNull($data['deployed_at'] ?? null),
        ];
    }

    private static function fromGit(): array
    {
        $commit = null;
        $head = @file_get_contents(base_path('.git/HEAD'));

        if ($head !== false) {
            $head = trim($head);
            if (str_starts_with($head, 'ref: ')) {
                $ref = @file_get_contents(base_path('.git/'.substr($head, 5)));
                $commit = $ref !== false ? trim($ref) : null;
            } else {
                $commit = $head; // detached HEAD
            }
        }

        return [
            'commit' => self::stringOrNull($commit),
            'build' => null,
            'deployed_at' => null,
        ];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
