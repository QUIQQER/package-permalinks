<?php

/**
 * This file contains \QUI\Permalinks\Permalink
 */

namespace QUI\Permalinks;

use QUI;

/**
 * Permalink class
 *
 * @author www.pcsg.de (Henning Leutz)
 * @author Jan Wennrich (PCSG)
 */
class Permalink
{
    /**
     * Set the permalink for a Site
     *
     * @param \QUI\Projects\Site $Site
     * @param string $permalink
     *
     * @return boolean
     *
     * @throws \QUI\Exception
     */
    public static function setPermalinkForSite($Site, $permalink)
    {
        if ($Site->getId() === 1) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'quiqqer/permalinks',
                    'exception.firstChild.cant.have.permalink'
                )
            );
        }

        $Project = $Site->getproject();
        $table   = QUI::getDBProjectTableName('permalinks', $Project, false);

        $hasPermalink    = false;
        $permalinkExists = false;

        // has the site a permalink?
        try {
            self::getPermalinkForSite($Site);
            $hasPermalink = true;
        } catch (QUI\Exception $Exception) {
        }

        if ($hasPermalink) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'quiqqer/permalinks',
                    'exception.couldNotSet.site.has.permalink'
                ),
                409
            );
        }

        // does the permalink exist?
        try {
            self::getSiteByPermalink($Project, $permalink);
            $permalinkExists = true;
        } catch (QUI\Exception $Exception) {
            // permalink does not exist, everything is ok
        }

        if ($permalinkExists) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'quiqqer/permalinks',
                    'exception.couldNotSet.already.exists'
                ),
                409
            );
        }


        // @TODO permalink prüfen ob dieser verwendet werden darf

        QUI::getDataBase()->insert($table, [
            'id'   => $Site->getId(),
            'lang' => $Project->getLang(),
            'link' => $permalink
        ]);

        return true;
    }

    /**
     * Return the permalink for a Site
     *
     * @param \QUI\Projects\Site $Site
     *
     * @throws \QUI\Exception
     * @return string
     */
    public static function getPermalinkForSite($Site)
    {
        $Project = $Site->getProject();
        $table   = QUI::getDBProjectTableName('permalinks', $Project, false);

        $result = QUI::getDataBase()->fetch([
            'from'  => $table,
            'where' => [
                'id'   => $Site->getId(),
                'lang' => $Project->getLang()
            ],
            'limit' => 1
        ]);

        if (!isset($result[0])) {
            throw new QUI\Exception(
                QUI::getLocale()->get(
                    'quiqqer/permalinks',
                    'exception.not.found'
                ),
                404
            );
        }

        return $result[0]['link'];
    }

    /**
     * Return the Site for a specific permalink
     *
     * @param \QUI\Projects\Project $Project
     * @param string $url
     *
     * @throws \QUI\Exception
     * @return \QUI\Projects\Site
     */
    public static function getSiteByPermalink(QUI\Projects\Project $Project, $url)
    {
        $table = QUI::getDBProjectTableName('permalinks', $Project, false);

        $result = QUI::getDataBase()->fetch([
            'from'  => $table,
            'where' => [
                'link' => $url
            ],
            'limit' => 1
        ]);


        if (!isset($result[0])) {
            $params = explode(QUI\Rewrite::URL_PARAM_SEPARATOR, $url);
            $url    = $params[0] . QUI\Rewrite::getDefaultSuffix();

            $result = QUI::getDataBase()->fetch([
                'from'  => $table,
                'where' => [
                    'link' => $url
                ],
                'limit' => 1
            ]);

            if (isset($result[0])) {
                $_Project = QUI::getProjectManager()->getProject(
                    $Project->getName(),
                    $result[0]['lang']
                );

                return $_Project->get($result[0]['id']);
            }

            throw new QUI\Exception(
                QUI::getLocale()->get('quiqqer/system', 'exception.site.not.found'),
                404
            );
        }

        $PermalinkProject = \QUI::getProjectManager()->getProject(
            $Project->getName(),
            $result[0]['lang']
        );

        return $PermalinkProject->get($result[0]['id']);
    }

    /**
     * Delete the permalink for a site
     *
     * @param \QUI\Projects\Site $Site
     *
     * @throws \QUI\Exception
     */
    public static function deletePermalinkForSite($Site)
    {
        $Project = $Site->getProject();
        $table   = QUI::getDBProjectTableName('permalinks', $Project, false);

        QUI::getDataBase()->delete($table, [
            'id'   => $Site->getId(),
            'lang' => $Project->getLang()
        ]);
    }

    /**
     * Events
     */

    /**
     * Event : on site save
     *
     * @param \QUI\Projects\Site\Edit $Site
     */
    public static function onSiteSaveBefore($Site)
    {
        $permalink = $Site->getAttribute('quiqqer.permalinks.site.permalink');
        $permalink = QUI\Projects\Site\Utils::clearUrl($permalink, $Site->getProject());

        $Site->setAttribute('quiqqer.permalinks.site.permalink', $permalink);
    }

    /**
     * Event : on site save
     *
     * @param \QUI\Projects\Site\Edit $Site
     */
    public static function onSave($Site)
    {
        if (!$Site->getAttribute('quiqqer.permalinks.site.permalink')) {
            return;
        }

        $permalink = $Site->getAttribute('quiqqer.permalinks.site.permalink');
        $permalink = QUI\Projects\Site\Utils::clearUrl($permalink, $Site->getProject());

        try {
            $oldLink = self::getPermalinkForSite($Site);

            if ($oldLink == $permalink) {
                return;
            }
        } catch (QUI\Exception $Exception) {
        }

        try {
            self::setPermalinkForSite($Site, $permalink);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            QUI::getMessagesHandler()->addError(
                QUI::getLocale()->get(
                    'quiqqer/permalinks',
                    'message.could.not.set'
                )
            );
        }
    }

    /**
     * Event : on site save
     *
     * @param \QUI\Projects\Site\Edit $Site
     */
    public static function onLoad($Site)
    {
        // if permalink exists, set the meta canonical
        try {
            $link = self::getPermalinkForSite($Site);

            if (empty($link)) {
                return;
            }

            // for the admin
            $Site->setAttribute('quiqqer.permalinks.site.permalink', $link);

            // canonical setzen
            $Site->setAttribute('canonical', $link);
        } catch (QUI\Exception $Exception) {
        }
    }

    /**
     * Event : on request
     *
     * @param \QUI\Rewrite $Rewrite
     * @param string $url
     */
    public static function onRequest(QUI\Rewrite $Rewrite, $url)
    {
        // media files are irrelevant
        if (strpos($url, 'media/cache') !== false) {
            return;
        }

        if (empty($url)) {
            return;
        }

        try {
            $Project = $Rewrite->getProject();
            $Site    = self::getSiteByPermalink($Project, $url);
            $Rewrite->setSite($Site);
        } catch (QUI\Exception $Exception) {
        }
    }
}
