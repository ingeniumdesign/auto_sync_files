<?php

declare(strict_types=1);

namespace ID\AutoSyncFiles\Task;

use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class DownloadAndExtractTaskAdditionalFieldProvider extends AbstractAdditionalFieldProvider
{
    private const LL = 'LLL:EXT:auto_sync_files/Resources/Private/Language/locallang.xlf:';

    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule): array
    {
        $additionalFields = [];

        $basePath = Environment::getPublicPath();
        $placeholderUrl = $this->translate('field.downloadUrl.placeholder.archive');
        $placeholderPath = $basePath . '/fileadmin/user_upload/extracted_files/';

        // ── Download URL ─────────────────────────────────────────────────
        $taskInfo['auto_sync_files_file_url'] = $task instanceof DownloadAndExtractTask
            ? $task->auto_sync_files_file_url
            : ($taskInfo['auto_sync_files_file_url'] ?? '');
        $additionalFields['auto_sync_files_file_url'] = [
            'code'  => sprintf(
                '<input class="form-control" type="text" name="tx_scheduler[auto_sync_files_file_url]" id="auto_sync_files_file_url" placeholder="%s" value="%s" size="30" />',
                htmlspecialchars($placeholderUrl),
                htmlspecialchars($taskInfo['auto_sync_files_file_url'])
            ),
            'label' => self::LL . 'field.downloadUrl.label',
        ];

        // ── Local Path ───────────────────────────────────────────────────
        $taskInfo['auto_sync_files_local_path'] = $task instanceof DownloadAndExtractTask
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

        // ── Replace folder contents (NEU in 12.0.6) ──────────────────────
        $taskInfo['auto_sync_files_replace_folder_contents'] = $task instanceof DownloadAndExtractTask
            ? $task->auto_sync_files_replace_folder_contents
            : ($taskInfo['auto_sync_files_replace_folder_contents'] ?? 'off');
        $replaceChecked = ($taskInfo['auto_sync_files_replace_folder_contents'] === 'on') ? 'checked' : '';
        $replaceCheckbox = $this->translate('field.replaceMode.checkbox');
        $replaceHelpEnabled = $this->translate('field.replaceMode.help.enabled');
        $replaceHelpDisabled = $this->translate('field.replaceMode.help.disabled');
        $replaceHelpNote = $this->translate('field.replaceMode.help.note');
        $additionalFields['auto_sync_files_replace_folder_contents'] = [
            'code'  => sprintf(
                '<div class="form-check">
                    <input class="form-check-input" type="checkbox" name="tx_scheduler[auto_sync_files_replace_folder_contents]" id="auto_sync_files_replace_folder_contents" value="on" %s />
                    <label class="form-check-label" for="auto_sync_files_replace_folder_contents">
                        <strong>%s</strong><br>
                        <small class="text-muted">
                            %s<br>
                            %s<br>
                            <em>%s</em>
                        </small>
                    </label>
                </div>',
                $replaceChecked,
                htmlspecialchars($replaceCheckbox),
                htmlspecialchars($replaceHelpEnabled),
                htmlspecialchars($replaceHelpDisabled),
                htmlspecialchars($replaceHelpNote)
            ),
            'label' => self::LL . 'field.replaceMode.label',
        ];

        // ── Clear Cache ──────────────────────────────────────────────────
        $taskInfo['auto_sync_files_clear_cache'] = $task instanceof DownloadAndExtractTask
            ? $task->auto_sync_files_clear_cache
            : ($taskInfo['auto_sync_files_clear_cache'] ?? 'on');
        $clearCacheChecked = ($taskInfo['auto_sync_files_clear_cache'] === 'on') ? 'checked' : '';
        $clearCacheLabel = $this->translate('field.clearCache.checkbox');
        $additionalFields['auto_sync_files_clear_cache'] = [
            'code'  => sprintf(
                '<div class="form-check">
                    <input class="form-check-input" type="checkbox" name="tx_scheduler[auto_sync_files_clear_cache]" id="auto_sync_files_clear_cache" value="on" %s />
                    <label class="form-check-label" for="auto_sync_files_clear_cache">%s</label>
                </div>',
                $clearCacheChecked,
                htmlspecialchars($clearCacheLabel)
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
        if ($task instanceof DownloadAndExtractTask) {
            $task->auto_sync_files_file_url = (string)$submittedData['auto_sync_files_file_url'];
            $task->auto_sync_files_local_path = (string)$submittedData['auto_sync_files_local_path'];
            $task->auto_sync_files_replace_folder_contents = isset($submittedData['auto_sync_files_replace_folder_contents'])
                ? (string)$submittedData['auto_sync_files_replace_folder_contents']
                : 'off';
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
