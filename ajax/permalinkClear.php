<?php

QUI::getAjax()->registerFunction(
    'package_quiqqer_permalinks_ajax_permalinkClear',
    /**
     * Clear a site name
     *
     * @param string $project
     * @param string $name
     *
     * @return string
     */
    function ($project, $name) {
        return QUI\Permalinks\Permalink::clearPermaLinkUrl(
            $name,
            QUI::getProjectManager()->decode($project)
        );
    },
    array('project', 'name'),
    'Permission::checkAdminUser'
);
