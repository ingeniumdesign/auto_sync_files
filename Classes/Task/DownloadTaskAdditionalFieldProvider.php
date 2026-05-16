<?php

declare(strict_types=1);

namespace ID\AutoSyncFiles\Task;

use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class DownloadTaskAdditionalFieldProvider extends AbstractAdditionalFieldProvider
{
    private const LL = 'LLL:EXT:auto_sync_files/Resources/Private/Language/locallang.xlf:';

    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule): array
    {
        $additionalFields = [];

        $basePath = Environment::getPublicPath();
        $placeholderUrl = $this->translate('field.downloadUrl.placeholder.file');
        $placeholderPath = $basePath . '/fileadmin/Templates/Assets/JavaScript/file.js';

        // ── Download URL ─────────────────────────────────────────────────
        $taskInfo['auto_sync_files_file_url'] = $task instanceof DownloadTask
            ? $task->auto_sync_files_file_url
            : ($taskInfo['auto_sync_files_file_url'] ?? '');
        $additionalFields['auto_sync_files_file_url'] = [
            'code'  => sprintf(
                '<input class="form-control" type="text" name="tx_scheduler[auto_sync_files_file_url]" id="auto_sync_files_file_url" placeholder="%s" value="%s" size="30" />',
                htmlspecialchars($placeholderUrl),
                htmlspecialchars($taskInfo['auto_sync_files_file_url'])
            ),
            'label' => self::LL . 'field.downloadUrl.labelWithExample',
        ];

        // ── Local Path ───────────────────────────────────────────────────
        $taskInfo['auto_sync_files_local_path'] = $task instanceof DownloadTask
            ? $task->auto_sync_files_local_path
            : ($taskInfo['auto_sync_files_local_path'] ?? '');
        $additionalFields['auto_sync_files_local_path'] = [
            'code'  => sprintf(
                '<input class="form-control" type="text" name="tx_scheduler[auto_sync_files_local_path]" id="auto_sync_files_local_path" placeholder="%s" value="%s" size="30" />',
                htmlspecialchars($placeholderPath),
                htmlspecialchars($taskInfo['auto_sync_files_local_path'])
            ),
            'label' => self::LL . 'field.localPath.label',
        ];

        // ── Clear Cache ──────────────────────────────────────────────────
        $taskInfo['auto_sync_files_clear_cache'] = $task instanceof DownloadTask
            ? $task->auto_sync_files_clear_cache
            : ($taskInfo['auto_sync_files_clear_cache'] ?? 'on');
        $clearCacheChecked = ($taskInfo['auto_sync_files_clear_cache'] === 'on') ? 'checked' : '';
        $checkboxLabel = $this->translate('field.clearCache.checkbox');
        $additionalFields['auto_sync_files_clear_cache'] = [
            'code'  => sprintf(
                '<div class="form-check">
                    <input class="form-check-input" type="checkbox" name="tx_scheduler[auto_sync_files_clear_cache]" id="auto_sync_files_clear_cache" value="on" %s />
                    <label class="form-check-label" for="auto_sync_files_clear_cache">%s</label>
                </div>',
                $clearCacheChecked,
                htmlspecialchars($checkboxLabel)
            ),
            'label' => self::LL . 'field.clearCache.label',
        ];

        return $additionalFields;
    }

    public function validateAdditionalFields(array &$submittedData, SchedulerModuleController $schedulerModule): bool
    {
        $submittedData['auto_sync_files_file_url'] = trim($submittedData['auto_sync_files_file_url'] ?? '');
        $submittedData['auto_sync_files_local_path'] = trim($submittedData['auto_sync_files_local_path'] ?? '');

        $valid = true;

        if ($submittedData['auto_sync_files_file_url'] === '') {
            $this->addMessage($this->translate('validation.url.required'), ContextualFeedbackSeverity::ERROR);
            $valid = false;
        } elseif (!$this->isValidDownloadUrl($submittedData['auto_sync_files_file_url'])) {
            $this->addMessage($this->translate('validation.url.invalid'), ContextualFeedbackSeverity::ERROR);
            $valid = false;
        }

        if ($submittedData['auto_sync_files_local_path'] === '') {
            $this->addMessage($this->translate('validation.path.required'), ContextualFeedbackSeverity::ERROR);
            $valid = false;
        }

        return $valid;
    }

    public function saveAdditionalFields(array $submittedData, AbstractTask $task): void
    {
        if ($task instanceof DownloadTask) {
            $task->auto_sync_files_file_url = (string)$submittedData['auto_sync_files_file_url'];
            $task->auto_sync_files_local_path = (string)$submittedData['auto_sync_files_local_path'];
            $task->auto_sync_files_clear_cache = isset($submittedData['auto_sync_files_clear_cache'])
                ? (string)$submittedData['auto_sync_files_clear_cache']
                : 'off';
        }
    }

    private function translate(string $key, ?array $arguments = null): string
    {
        $translated = LocalizationUtility::translate($key, 'auto_sync_files', $arguments);
        return $translated ?? $key;
    }

    private function isValidDownloadUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true);
    }
}
