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

    QUI\Meta\Permalink::deletePermalinkForSite($Site);

    try {
        return QUI\Meta\Permalink::getPermalinkFor($Site);
    } catch (QUI\Exception $Exception) {
        return '';
    }
}

QUI::$Ajax->register(
    'package_quiqqer_meta_ajax_permalink_delete',
    array('project', 'lang', 'id')
);
