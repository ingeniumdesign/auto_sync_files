<?php

declare(strict_types=1);

namespace ID\AutoSyncFiles\Task;

use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\SchedulerManagementAction;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class DownloadTaskAdditionalFieldProvider extends AbstractAdditionalFieldProvider
{
    private const LL = 'LLL:EXT:auto_sync_files/Resources/Private/Language/locallang.xlf:';

    /**
     * Eigene Feld-IDs je Task-Typ: Im Formular "Task anlegen" stehen die Felder aller Task-Typen
     * gleichzeitig im HTML. Gleiche IDs wuerden Labels auf das Feld des anderen Task-Typs zeigen lassen.
     * Die Feldnamen (tx_scheduler[auto_sync_files_*]) bleiben gleich.
     */
    private const ID_PREFIX = 'auto_sync_files_download_';

    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule): array
    {
        // $taskInfo enthaelt in TYPO3 13.4 nach einem Validierungsfehler die abgeschickten Werte und in
        // TYPO3 14.3 beim Bearbeiten die gespeicherten Werte (Schalter dann 'on' oder 'off'). Sonst gelten die
        // Werte des gespeicherten Tasks bzw. beim Anlegen die Standardwerte. TYPO3 14.3 uebergibt auch beim
        // Anlegen ein leeres Task-Objekt, deshalb entscheidet die aktuelle Aktion.
        $isNewTask = $schedulerModule->getCurrentAction() === SchedulerManagementAction::ADD;
        $storedTask = !$isNewTask && $task instanceof DownloadTask ? $task : null;
        $isSubmitted = array_key_exists('auto_sync_files_file_url', $taskInfo);

        $url = $isSubmitted
            ? trim((string)$taskInfo['auto_sync_files_file_url'])
            : ($storedTask?->auto_sync_files_file_url ?? '');
        $localPath = $isSubmitted
            ? trim((string)($taskInfo['auto_sync_files_local_path'] ?? ''))
            : ($storedTask?->auto_sync_files_local_path ?? '');
        $clearCache = $isSubmitted
            ? ($taskInfo['auto_sync_files_clear_cache'] ?? '') === 'on'
            : ($storedTask === null || $storedTask->auto_sync_files_clear_cache === 'on');

        $additionalFields = [];

        // ── Download URL ─────────────────────────────────────────────────
        $additionalFields[self::ID_PREFIX . 'file_url'] = [
            'code'  => sprintf(
                '<input class="form-control" type="text" name="tx_scheduler[auto_sync_files_file_url]" id="%s" placeholder="%s" value="%s">',
                self::ID_PREFIX . 'file_url',
                htmlspecialchars($this->translate('field.downloadUrl.placeholder.file')),
                htmlspecialchars($url)
            ),
            'label' => self::LL . 'field.downloadUrl.labelWithExample',
            'type'  => 'input',
        ];

        // ── Local Path ───────────────────────────────────────────────────
        $additionalFields[self::ID_PREFIX . 'local_path'] = [
            'code'  => sprintf(
                '<input class="form-control" type="text" name="tx_scheduler[auto_sync_files_local_path]" id="%s" placeholder="%s" value="%s">',
                self::ID_PREFIX . 'local_path',
                htmlspecialchars(Environment::getPublicPath() . '/fileadmin/Templates/Assets/JavaScript/file.js'),
                htmlspecialchars($localPath)
            ),
            'label' => self::LL . 'field.localPath.label',
            'type'  => 'input',
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

        // addMessage() ist die Methode von AbstractAdditionalFieldProvider. Der Extension Scanner verwechselt sie
        // mit dem laengst entfernten SchedulerModuleController::addMessage(), daher die Scanner-Markierung unten.
        $valid = true;

        if ($submittedData['auto_sync_files_file_url'] === '') {
            // @extensionScannerIgnoreLine
            $this->addMessage($this->translate('validation.url.required'), ContextualFeedbackSeverity::ERROR);
            $valid = false;
        } elseif (!$this->isValidDownloadUrl($submittedData['auto_sync_files_file_url'])) {
            // @extensionScannerIgnoreLine
            $this->addMessage($this->translate('validation.url.invalid'), ContextualFeedbackSeverity::ERROR);
            $valid = false;
        }

        if ($submittedData['auto_sync_files_local_path'] === '') {
            // @extensionScannerIgnoreLine
            $this->addMessage($this->translate('validation.path.required'), ContextualFeedbackSeverity::ERROR);
            $valid = false;
        }

        return $valid;
    }

    public function saveAdditionalFields(array $submittedData, AbstractTask $task): void
    {
        if ($task instanceof DownloadTask) {
            // Erneut trimmen: Der Scheduler speichert die Werte aus dem Request, nicht die in
            // validateAdditionalFields() bereinigte Kopie.
            $task->auto_sync_files_file_url = trim((string)$submittedData['auto_sync_files_file_url']);
            $task->auto_sync_files_local_path = trim((string)$submittedData['auto_sync_files_local_path']);
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
