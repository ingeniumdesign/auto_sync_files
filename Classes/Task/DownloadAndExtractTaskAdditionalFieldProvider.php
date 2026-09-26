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

    /**
     * Eigene Feld-IDs je Task-Typ: Im Formular "Task anlegen" stehen die Felder aller Task-Typen
     * gleichzeitig im HTML. Gleiche IDs wuerden Labels auf das Feld des anderen Task-Typs zeigen lassen.
     * Die Feldnamen (tx_scheduler[auto_sync_files_*]) bleiben gleich.
     */
    private const ID_PREFIX = 'auto_sync_files_extract_';

    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule): array
    {
        // Nach einem Validierungsfehler zeigt der Scheduler das Formular erneut an: dann die abgeschickten
        // Werte behalten. Sonst die gespeicherten Werte des Tasks bzw. beim Anlegen die Standardwerte.
        $storedTask = $task instanceof DownloadAndExtractTask ? $task : null;
        $isSubmitted = array_key_exists('auto_sync_files_file_url', $taskInfo);

        $url = $isSubmitted
            ? trim((string)$taskInfo['auto_sync_files_file_url'])
            : ($storedTask?->auto_sync_files_file_url ?? '');
        $localPath = $isSubmitted
            ? trim((string)($taskInfo['auto_sync_files_local_path'] ?? ''))
            : ($storedTask?->auto_sync_files_local_path ?? '');
        $replaceMode = $isSubmitted
            ? isset($taskInfo['auto_sync_files_replace_folder_contents'])
            : $storedTask?->auto_sync_files_replace_folder_contents === 'on';
        $clearCache = $isSubmitted
            ? isset($taskInfo['auto_sync_files_clear_cache'])
            : ($storedTask === null || $storedTask->auto_sync_files_clear_cache === 'on');

        $additionalFields = [];

        // ── Download URL ─────────────────────────────────────────────────
        $additionalFields[self::ID_PREFIX . 'file_url'] = [
            'code'  => sprintf(
                '<input class="form-control" type="text" name="tx_scheduler[auto_sync_files_file_url]" id="%s" placeholder="%s" value="%s">',
                self::ID_PREFIX . 'file_url',
                htmlspecialchars($this->translate('field.downloadUrl.placeholder.archive')),
                htmlspecialchars($url)
            ),
            'label' => self::LL . 'field.downloadUrl.label',
            'type'  => 'input',
        ];

        // ── Local Path ───────────────────────────────────────────────────
        $additionalFields[self::ID_PREFIX . 'local_path'] = [
            'code'  => sprintf(
                '<input class="form-control" type="text" name="tx_scheduler[auto_sync_files_local_path]" id="%s" placeholder="%s" value="%s">',
                self::ID_PREFIX . 'local_path',
                htmlspecialchars(Environment::getPublicPath() . '/fileadmin/user_upload/extracted_files/'),
                htmlspecialchars($localPath)
            ),
            'label' => self::LL . 'field.localPath.label',
            'type'  => 'input',
        ];

        // ── Replace folder contents ──────────────────────────────────────
        // Der Hilfetext steht im Feld-Code und nicht unter 'description': Diese zeigt TYPO3 13.4
        // bei 'checkToggle' erst ab einer spaeteren 13.4-Version an.
        $additionalFields[self::ID_PREFIX . 'replace_folder_contents'] = [
            'code'  => sprintf(
                '<input class="form-check-input" type="checkbox" role="switch" name="tx_scheduler[auto_sync_files_replace_folder_contents]" id="%1$s" value="on"%2$s>'
                . '<label class="form-check-label" for="%1$s">%3$s</label>'
                . '<div class="form-text">%4$s<br>%5$s<br><em>%6$s</em></div>',
                self::ID_PREFIX . 'replace_folder_contents',
                $replaceMode ? ' checked' : '',
                htmlspecialchars($this->translate('field.replaceMode.checkbox')),
                htmlspecialchars($this->translate('field.replaceMode.help.enabled')),
                htmlspecialchars($this->translate('field.replaceMode.help.disabled')),
                htmlspecialchars($this->translate('field.replaceMode.help.note'))
            ),
            'label' => self::LL . 'field.replaceMode.label',
            'type'  => 'checkToggle',
        ];

        // ── Clear Cache ──────────────────────────────────────────────────
        $additionalFields[self::ID_PREFIX . 'clear_cache'] = [
            'code'  => sprintf(
                '<input class="form-check-input" type="checkbox" role="switch" name="tx_scheduler[auto_sync_files_clear_cache]" id="%1$s" value="on"%2$s>'
                . '<label class="form-check-label" for="%1$s">%3$s</label>',
                self::ID_PREFIX . 'clear_cache',
                $clearCache ? ' checked' : '',
                htmlspecialchars($this->translate('field.clearCache.checkbox'))
            ),
            'label' => self::LL . 'field.clearCache.label',
            'type'  => 'checkToggle',
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
        } elseif (($submittedData['auto_sync_files_replace_folder_contents'] ?? '') === 'on'
            && $this->isProtectedReplaceTarget($submittedData['auto_sync_files_local_path'])
        ) {
            $this->addMessage($this->translate('validation.replaceTargetNotAllowed'), ContextualFeedbackSeverity::ERROR);
            $valid = false;
        }

        return $valid;
    }

    public function saveAdditionalFields(array $submittedData, AbstractTask $task): void
    {
        if ($task instanceof DownloadAndExtractTask) {
            // Erneut trimmen: Der Scheduler speichert die Werte aus dem Request, nicht die in
            // validateAdditionalFields() bereinigte Kopie.
            $task->auto_sync_files_file_url = trim((string)$submittedData['auto_sync_files_file_url']);
            $task->auto_sync_files_local_path = trim((string)$submittedData['auto_sync_files_local_path']);
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

    /**
     * Gleiche Regel wie DownloadAndExtractTask::isProtectedReplaceTarget():
     * Replace-Mode ist fuer den TYPO3-Public-Root selbst und typo3temp/ gesperrt.
     */
    private function isProtectedReplaceTarget(string $targetPath): bool
    {
        $target = realpath($targetPath);
        if ($target === false) {
            return false;
        }
        $publicRoot = realpath(Environment::getPublicPath());
        $tempRoot = realpath(Environment::getPublicPath() . '/typo3temp');

        return $target === $publicRoot || ($tempRoot !== false && $target === $tempRoot);
    }
}
