<?php

/**
 * Delete a permalink
 *
 * @param string $project
 * @param string $lang
 * @param integer $id
 * @return string
 */
function package_quiqqer_permalinks_ajax_delete($project, $lang, $id)
{
    $Project = QUI::getProject($project, $lang);
    $Site    = $Project->get($id);

    QUI\Permalinks\Permalink::deletePermalinkForSite($Site);

    try {
        return QUI\Permalinks\Permalink::getPermalinkForSite($Site);
    } catch (QUI\Exception $Exception) {
        return '';
    }
}

QUI::$Ajax->register(
    'package_quiqqer_permalinks_ajax_delete',
    array('project', 'lang', 'id')
);
