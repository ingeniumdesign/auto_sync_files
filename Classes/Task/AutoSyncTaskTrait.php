<?php

declare(strict_types=1);

namespace ID\AutoSyncFiles\Task;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Gemeinsame Helper-Methoden fuer die Scheduler-Tasks der Extension.
 * Eingebunden via `use AutoSyncTaskTrait;` in DownloadTask und DownloadAndExtractTask.
 *
 * Erwartet, dass die nutzende Klasse LoggerAwareInterface implementiert
 * (erfuellt durch TYPO3\CMS\Scheduler\Task\AbstractTask via LoggerAwareTrait).
 */
trait AutoSyncTaskTrait
{
    /**
     * Validiert die Download-URL anhand einer Schema-Whitelist (nur http/https).
     * Verhindert SSRF-Angriffe ueber file://, phar://, gopher:// und aehnliche Wrapper.
     */
    protected function isValidDownloadUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }

    /**
     * Stellt sicher, dass der Zielpfad innerhalb des TYPO3 Public Path liegt.
     */
    protected function isPathWithinPublicRoot(string $targetPath): bool
    {
        $publicRoot = realpath(Environment::getPublicPath());
        if ($publicRoot === false) {
            return false;
        }

        if (file_exists($targetPath)) {
            $resolved = realpath($targetPath);
        } else {
            $parent = realpath(dirname($targetPath));
            if ($parent === false) {
                return false;
            }
            $resolved = $parent . DIRECTORY_SEPARATOR . basename($targetPath);
        }

        if ($resolved === false || $resolved === '') {
            return false;
        }

        return $resolved === $publicRoot
            || str_starts_with($resolved, $publicRoot . DIRECTORY_SEPARATOR);
    }

    /**
     * Loest einen Locallang-Key in einen String auf.
     * Wenn der Key nicht gefunden wird, wird der Key selbst zurueckgegeben.
     */
    protected function translate(string $key, ?array $arguments = null): string
    {
        $translated = LocalizationUtility::translate($key, 'auto_sync_files', $arguments);
        return $translated ?? $key;
    }

    /**
     * Loggt eine Fehlernachricht via Locallang-Key. Komfort-Wrapper um log() + translate().
     */
    protected function logKey(string $key, ?array $arguments = null): void
    {
        $this->log($this->translate($key, $arguments));
    }

    /**
     * Kombiniert logKey() + return false. Spart das Pattern
     *     $this->logKey('error.foo', [$bar]);
     *     return false;
     * an vielen Stellen in den Task-Klassen.
     */
    protected function logAndReturnFalse(string $key, ?array $arguments = null): bool
    {
        $this->logKey($key, $arguments);
        return false;
    }

    /**
     * Loggt eine Fehlernachricht ueber den TYPO3 LogManager.
     * Der $logger wird vom Scheduler via LoggerAwareInterface injiziert.
     */
    protected function log(string $message): void
    {
        $this->logger?->error($message);
    }
}
