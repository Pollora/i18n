<?php

declare(strict_types=1);

/**
 * Runs tests/Fixtures/request.php, one simulated web request, in a process of its own.
 *
 * @return array{result: string, tooling: list<string>}
 */
function simulateRequest(string $mode): array
{
    $process = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', __DIR__.'/Fixtures/request.php', $mode], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    if (! is_resource($process)) {
        throw new RuntimeException('The simulated request could not start.');
    }

    $output = (string) stream_get_contents($pipes[1]);
    $errors = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    if (proc_close($process) !== 0) {
        throw new RuntimeException('The simulated request failed: '.$errors.$output);
    }

    /** @var array{result: string, tooling: list<string>} */
    return json_decode($output, true, flags: JSON_THROW_ON_ERROR);
}
