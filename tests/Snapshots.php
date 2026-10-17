<?php

namespace NineteenNinetyFour\CookieConnect\Core\Tests;

/**
 * Compares output with a file in tests/Fixtures/snapshots. A missing file is
 * written and the test fails, so each new snapshot is looked at once. Run
 * with UPDATE_SNAPSHOTS=1 to rewrite them after a deliberate change.
 */
trait Snapshots
{
    protected function assertMatchesSnapshot(string $name, string $actual): void
    {
        $file = __DIR__.'/Fixtures/snapshots/'.$name.'.html';
        $actual = rtrim($actual)."\n";

        if (getenv('UPDATE_SNAPSHOTS') || ! is_file($file)) {
            $new = ! is_file($file);
            @mkdir(dirname($file), 0777, true);
            file_put_contents($file, $actual);

            if ($new && ! getenv('UPDATE_SNAPSHOTS')) {
                $this->fail("Wrote a new snapshot, {$name}. Check it, then run the tests again.");
            }
        }

        $this->assertSame((string) file_get_contents($file), $actual, "Output differs from snapshot {$name}. If that's intended, run with UPDATE_SNAPSHOTS=1.");
    }
}
