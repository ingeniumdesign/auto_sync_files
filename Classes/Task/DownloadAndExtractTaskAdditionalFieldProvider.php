<?php
declare(strict_types=1);

namespace ID\AutoSyncFiles\Task;

use TYPO3\CMS\Scheduler\AbstractAdditionalFieldProvider;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

class DownloadAndExtractTaskAdditionalFieldProvider extends AbstractAdditionalFieldProvider
{
    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule): array
    {
        $additionalFields = [];

        // Download URL field
        $taskInfo['auto_sync_files_file_url'] = $task instanceof DownloadAndExtractTask
            ? $task->auto_sync_files_file_url
            : ($taskInfo['auto_sync_files_file_url'] ?? '');
        $additionalFields['auto_sync_files_file_url'] = [
            'code'  => sprintf(
                '<input class="form-control" type="text" name="tx_scheduler[auto_sync_files_file_url]" id="auto_sync_files_file_url" placeholder="e.g., https://example.com/archive.zip" value="%s" size="30" />',
                htmlspecialchars($taskInfo['auto_sync_files_file_url'])
            ),
            'label' => 'Download URL'
        ];

        // Local Path field with warning that all files in this folder will be deleted
        $taskInfo['auto_sync_files_local_path'] = $task instanceof DownloadAndExtractTask
            ? $task->auto_sync_files_local_path
            : ($taskInfo['auto_sync_files_local_path'] ?? '');
        $additionalFields['auto_sync_files_local_path'] = [
            'code'  => sprintf(
                '<input class="form-control" type="text" name="tx_scheduler[auto_sync_files_local_path]" id="auto_sync_files_local_path" placeholder="e.g., /var/www/html/path/to/extracted/" value="%s" size="30" />',
                htmlspecialchars($taskInfo['auto_sync_files_local_path'])
            ),
            'label' => 'Local Path (all files in this folder will be deleted!)',
        ];

        // Clear Cache field
        $taskInfo['auto_sync_files_clear_cache'] = $task instanceof DownloadAndExtractTask
            ? $task->auto_sync_files_clear_cache
            : ($taskInfo['auto_sync_files_clear_cache'] ?? 'on');
        $checked = ($taskInfo['auto_sync_files_clear_cache'] === 'on') ? 'checked' : '';
        $additionalFields['auto_sync_files_clear_cache'] = [
            'code'  => sprintf(
                '<div class="form-check">
                    <input class="form-check-input" type="checkbox" name="tx_scheduler[auto_sync_files_clear_cache]" id="auto_sync_files_clear_cache" value="on" %s />
                    <label class="form-check-label" for="auto_sync_files_clear_cache">Clear Cache after execution if files differ</label>
                </div>',
                $checked
            ),
            'label' => 'Clear Cache',
        ];

        return $additionalFields;
    }

    public function validateAdditionalFields(array &$submittedData, SchedulerModuleController $schedulerModule): bool
    {
        $submittedData['auto_sync_files_file_url'] = trim($submittedData['auto_sync_files_file_url'] ?? '');
        $submittedData['auto_sync_files_local_path'] = trim($submittedData['auto_sync_files_local_path'] ?? '');
        return !empty($submittedData['auto_sync_files_file_url']) && !empty($submittedData['auto_sync_files_local_path']);
    }

    public function saveAdditionalFields(array $submittedData, AbstractTask $task): void
    {
        if ($task instanceof DownloadAndExtractTask) {
            $task->auto_sync_files_file_url = (string)$submittedData['auto_sync_files_file_url'];
            $task->auto_sync_files_local_path = (string)$submittedData['auto_sync_files_local_path'];
            $task->auto_sync_files_clear_cache = (string)($submittedData['auto_sync_files_clear_cache'] ?? 'off');
        }
    }
}
