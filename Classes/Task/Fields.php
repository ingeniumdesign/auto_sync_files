<?php

namespace ID\Downloadscriptstask\Task;

class Fields implements \TYPO3\CMS\Scheduler\AdditionalFieldProviderInterface {
    public function getAdditionalFields(array &$taskInfo, $task, \TYPO3\CMS\Scheduler\Controller\SchedulerModuleController $parentObject) {

        // Initialize selected fields
        if (!isset($taskInfo['downloadscriptstask_file_url'])) {
            $taskInfo['downloadscriptstask_file_url'] = '';
            if ($parentObject->CMD === 'edit') {
                $taskInfo['downloadscriptstask_file_url'] = $task->downloadscriptstask_file_url;
            }
        }

        $fieldName = 'tx_scheduler[downloadscriptstask_file_url]';
        $fieldId = 'downloadscriptstask_file_url';
        $fieldValue = $taskInfo['downloadscriptstask_file_url'];
        $fieldHtml = '<input type="text" style="width: 50%" name="' . $fieldName . '" id="' . $fieldId . '" value="' . htmlspecialchars($fieldValue) . '" />';
        $additionalFields[$fieldId] = array(
            'code' => $fieldHtml,
            'label' => 'The URL of the File that is supposed to be downloaded',
            'cshKey' => '_MOD_tools_txschedulerM1X',
            'cshLabel' => $fieldId
        );

        // Initialize selected fields
        if (!isset($taskInfo['downloadscriptstask_local_path'])) {
            $taskInfo['downloadscriptstask_local_path'] = '';
            if ($parentObject->CMD === 'edit') {
                $taskInfo['downloadscriptstask_local_path'] = $task->downloadscriptstask_local_path;
            }
        }

        $fieldName = 'tx_scheduler[downloadscriptstask_local_path]';
        $fieldId = 'downloadscriptstask_local_path';
        $fieldValue = $taskInfo['downloadscriptstask_local_path'];
        $fieldHtml = '<input type="text" style="width: 50%" name="' . $fieldName . '" id="' . $fieldId . '" value="' . htmlspecialchars($fieldValue) . '" />';
        $additionalFields[$fieldId] = array(
            'code' => $fieldHtml,
            'label' => 'The absolute path the file should be saved to',
            'cshKey' => '_MOD_tools_txschedulerM1Y',
            'cshLabel' => $fieldId
        );

        // Initialize selected fields
        if (!isset($taskInfo['downloadscriptstask_clear_cache'])) {
            $taskInfo['downloadscriptstask_clear_cache'] = 'on';
            if ($parentObject->CMD === 'edit') {
                $taskInfo['downloadscriptstask_clear_cache'] = $task->downloadscriptstask_clear_cache;
            }
        }

        $fieldName = 'tx_scheduler[downloadscriptstask_clear_cache]';
        $fieldId = 'downloadscriptstask_clear_cache';
        $fieldHtml = '<input type="checkbox" name="' . $fieldName . '" id="' . $fieldId . '" value="on" ' . ($taskInfo['downloadscriptstask_clear_cache'] == 'on' ? 'checked' : '') . ' > 
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
        $submittedData['downloadscriptstask_local_path'] = trim($submittedData['downloadscriptstask_local_path']);
        $submittedData['downloadscriptstask_file_url'] = trim($submittedData['downloadscriptstask_file_url']);

        return !empty($submittedData['downloadscriptstask_local_path']) && !empty($submittedData['downloadscriptstask_file_url']);
    }
    public function saveAdditionalFields(array $submittedData, \TYPO3\CMS\Scheduler\Task\AbstractTask $task) {
        $task->downloadscriptstask_local_path = $submittedData['downloadscriptstask_local_path'];
        $task->downloadscriptstask_file_url = $submittedData['downloadscriptstask_file_url'];
        $task->downloadscriptstask_clear_cache = $submittedData['downloadscriptstask_clear_cache'];
    }
}