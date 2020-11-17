<?php
use QUI\System\Log;
/**
 * Clear a site name
 *
 * @param string $project
 * @param string $name
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_permalinks_ajax_permalinkClear',
    function ($project, $name) {
        return QUI\Permalinks\Permalink::clearPermaLinkUrl(
            $name,
            QUI::getProjectManager()->decode($project)
        );
    },
    array('project', 'name'),
    'Permission::checkAdminUser'
);
