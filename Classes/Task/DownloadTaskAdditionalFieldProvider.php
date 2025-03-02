<?php

namespace ID\AutoSyncFiles\Task;

class Fields implements \TYPO3\CMS\Scheduler\AdditionalFieldProviderInterface {
    public function getAdditionalFields(array &$taskInfo, $task, \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $parentObject) {

        // Initialize selected fields
        if (!isset($taskInfo['auto_sync_files_file_url'])) {
            $taskInfo['auto_sync_files_file_url'] = '';
            if ($parentObject->CMD === 'edit') {
                $taskInfo['auto_sync_files_file_url'] = $task->auto_sync_files_file_url;
            }
        }

        $fieldName = 'tx_scheduler[auto_sync_files_file_url]';
        $fieldId = 'auto_sync_files_file_url';
        $fieldValue = $taskInfo['auto_sync_files_file_url'];
        $fieldHtml = '<input type="text" style="width: 50%" name="' . $fieldName . '" id="' . $fieldId . '" value="' . htmlspecialchars($fieldValue) . '" />';
        $additionalFields[$fieldId] = array(
            'code' => $fieldHtml,
            'label' => 'The URL of the File that is supposed to be downloaded',
            'cshKey' => '_MOD_tools_txschedulerM1X',
            'cshLabel' => $fieldId
        );

        // Initialize selected fields
        if (!isset($taskInfo['auto_sync_files_local_path'])) {
            $taskInfo['auto_sync_files_local_path'] = '';
            if ($parentObject->CMD === 'edit') {
                $taskInfo['auto_sync_files_local_path'] = $task->auto_sync_files_local_path;
            }
        }

        $fieldName = 'tx_scheduler[auto_sync_files_local_path]';
        $fieldId = 'auto_sync_files_local_path';
        $fieldValue = $taskInfo['auto_sync_files_local_path'];
        $fieldHtml = '<input type="text" style="width: 50%" name="' . $fieldName . '" id="' . $fieldId . '" value="' . htmlspecialchars($fieldValue) . '" />';
        $additionalFields[$fieldId] = array(
            'code' => $fieldHtml,
            'label' => 'The absolute path the file should be saved to (Typo3-base-dir: "' . PATH_site . '")',
            'cshKey' => '_MOD_tools_txschedulerM1Y',
            'cshLabel' => $fieldId
        );

        // Initialize selected fields
        if (!isset($taskInfo['auto_sync_files_clear_cache'])) {
            $taskInfo['auto_sync_files_clear_cache'] = 'on';
            if ($parentObject->CMD === 'edit') {
                $taskInfo['auto_sync_files_clear_cache'] = $task->auto_sync_files_clear_cache;
            }
        }

        $fieldName = 'tx_scheduler[auto_sync_files_clear_cache]';
        $fieldId = 'auto_sync_files_clear_cache';
        $fieldHtml = '<input type="checkbox" name="' . $fieldName . '" id="' . $fieldId . '" value="on" ' . ($taskInfo['auto_sync_files_clear_cache'] == 'on' ? 'checked' : '') . ' > 
        <label for="' . $fieldId . '">Clear Cache after execution if files are different</label>';
        $additionalFields[$fieldId] = array(
            'code' => $fieldHtml,
            'label' => 'Clear Frontend-Cache after execution',
            'cshKey' => '_MOD_tools_txschedulerM1Z',
            'cshLabel' => $fieldId
        );


        return $additionalFields;
    }

    public function validateAdditionalFields(array &$submittedData, \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $parentObject) {
        $submittedData['auto_sync_files_local_path'] = trim($submittedData['auto_sync_files_local_path']);
        $submittedData['auto_sync_files_file_url'] = trim($submittedData['auto_sync_files_file_url']);

        return !empty($submittedData['auto_sync_files_local_path']) && !empty($submittedData['auto_sync_files_file_url']);
    }
    public function saveAdditionalFields(array $submittedData, \TYPO3\CMS\Scheduler\Task\AbstractTask $task) {
        $task->auto_sync_files_local_path = $submittedData['auto_sync_files_local_path'];
        $task->auto_sync_files_file_url = $submittedData['auto_sync_files_file_url'];
        $task->auto_sync_files_clear_cache = $submittedData['auto_sync_files_clear_cache'];
    }
}