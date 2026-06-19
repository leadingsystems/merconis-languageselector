<?php

namespace LeadingSystems\LanguageSelector;

use Contao\Database;
use Contao\Environment;
use Contao\Input;
use Contao\PageModel;
use Contao\System;

class LsController {

    private static array $cache_getCorrespondingLanguagesForCurrentRootPage = [];
    private static array $cache_getMainlanguagePageIDForPageID = [];

	public function getCorrespondingLanguagesForCurrentRootPage($pageID = false) {

        if (array_key_exists($pageID, self::$cache_getCorrespondingLanguagesForCurrentRootPage)) {
            return self::$cache_getCorrespondingLanguagesForCurrentRootPage[$pageID];
        }

		if (!$pageID) {
			global $objPage;
		} else {
			$objPage = PageModel::findWithDetails($pageID);
		}

		if ($objPage->rootId) {
			$objRootPage = Database::getInstance()->prepare("SELECT * FROM `tl_page` WHERE `id` = ?")
											->limit(1)
											->execute($objPage->rootId);
		} else {
			$objRootPage = $objPage;
		}

		$currentDomain = $objRootPage->dns;
		$languageGroupId = (int) $objRootPage->ls_cnc_languageSelector_languageGroup;

		$useLanguageGroup = false;
		$masterRootId = 0;

		if ($languageGroupId) {
			$useLanguageGroup = true;
			$masterRootId = $languageGroupId;
		} else {
			$objSlavesOfCurrent = Database::getInstance()->prepare(
				"SELECT id FROM `tl_page` WHERE `ls_cnc_languageSelector_languageGroup` = ? LIMIT 1"
			)->execute($objRootPage->id);

			if ($objSlavesOfCurrent->numRows) {
				$useLanguageGroup = true;
				$masterRootId = (int) $objRootPage->id;
			}
		}

		if ($useLanguageGroup) {
			$objGroupRootPages = Database::getInstance()->prepare(
				"SELECT * FROM `tl_page` WHERE `type` = 'root' AND `published` = 1"
				. " AND (`id` = ? OR `ls_cnc_languageSelector_languageGroup` = ?)"
				. " ORDER BY `sorting`"
			)->execute($masterRootId, $masterRootId);
		} else {
			$objGroupRootPages = Database::getInstance()->prepare(
				"SELECT * FROM `tl_page` WHERE `type` = 'root' AND `dns` = ? AND `published` = 1 ORDER BY `sorting`"
			)->execute($currentDomain);
		}

		$languagesForCurrentDomain = array();
		while ($objGroupRootPages->next()) {

            $targetAlias = ($objPage->type !== 'regular' || $objPage->language != $objGroupRootPages->language)
                ? $objGroupRootPages->row()['alias']
                : $objPage->alias;
            $obj_pageModel = PageModel::findByAlias($targetAlias);

            if (!isset($GLOBALS['merconis-languageselector_globals']['cache_language_files'][$objGroupRootPages->language])) {
                System::loadLanguageFile('languages', $objGroupRootPages->language, true);
                $GLOBALS['merconis-languageselector_globals']['cache_language_files'][$objGroupRootPages->language] = System::getContainer()->get('contao.intl.locales')->getLanguages();
                System::loadLanguageFile('languages', $objPage->language, true);
            }

			if (!in_array($objGroupRootPages->language, $languagesForCurrentDomain)) {
				$targetHref = $obj_pageModel->current()->getFrontendUrl();

				if ($useLanguageGroup && $objGroupRootPages->dns != $currentDomain) {
					$targetHref = $this->buildAbsoluteUrl($objGroupRootPages, $targetHref);
				}

				$languagesForCurrentDomain[$objGroupRootPages->language] = array(
					'alias' => $objPage->language != $objGroupRootPages->language ? $objGroupRootPages->alias : $objPage->alias,
					'id' => $objPage->language != $objGroupRootPages->language ? $objGroupRootPages->id : $objPage->id,
					'href' => $targetHref,
                    'languageTitle' => $GLOBALS['merconis-languageselector_globals']['cache_language_files'][$objGroupRootPages->language][$objGroupRootPages->language]
				);
			}
		}

		$isMainLanguagePage = false;
		if ($useLanguageGroup) {
			$isMainLanguagePage = ((int) $objRootPage->id === $masterRootId);
		} else {
			$isMainLanguagePage = (bool) $objRootPage->fallback;
		}

		$mainLanguageID = $isMainLanguagePage ? $objPage->id : $objPage->ls_cnc_languageSelector_correspondingMainLanguagePage;

		if ($mainLanguageID) {
			$objCorrespondingPages = Database::getInstance()->prepare("SELECT * FROM `tl_page` WHERE (`ls_cnc_languageSelector_correspondingMainLanguagePage` = ? OR `id` = ?) AND `published` = 1")
													->execute($mainLanguageID, $mainLanguageID);

			while ($objCorrespondingPages->next()) {
				$pageDetails = PageModel::findWithDetails($objCorrespondingPages->id);

				if ($useLanguageGroup) {
					if (!$this->belongsToSameLanguageGroup($pageDetails->rootId, $masterRootId)) {
						continue;
					}
				} else {
					if ($pageDetails->domain != $currentDomain) {
						continue;
					}
				}

				if (isset($languagesForCurrentDomain[$pageDetails->language])) {
					$languagesForCurrentDomain[$pageDetails->language]['alias'] = $objCorrespondingPages->alias;
					$languagesForCurrentDomain[$pageDetails->language]['id'] = $objCorrespondingPages->id;

					$queryString = '';
					$secondQueryString = '';
					if (isset($_GET) && is_array($_GET)) {
						$arrExcludedGetParameters = array('articles','auto_item','language');

						foreach ($_GET as $k => $v) {
							if (in_array($k, $arrExcludedGetParameters)) {
								continue;
							}

							if (!preg_match('/'.preg_quote($k, '/').'=/', Environment::get('request'))) {
								$queryString .= '/'.$k.'/'.Input::get($k);
							} else {
								$secondQueryString .= ($secondQueryString ? '&amp;' : '').$k.'='.Input::get($k);
							}
						}
					}

                    if(Input::get('auto_item')) {
                        $obj_targetPageCollection = PageModel::findById($pageDetails->pid);
                        if ($obj_targetPageCollection->current()->type === 'regular') {
                            $href = $obj_targetPageCollection->current()->getFrontendUrl();

                            $targetRootDns = $this->getRootPageDns($pageDetails->rootId);
                            if ($targetRootDns && $targetRootDns != $currentDomain) {
                                $href = $this->buildAbsoluteUrlFromRootId($pageDetails->rootId, $href);
                            }

                            $languagesForCurrentDomain[$pageDetails->language]['href'] = $href;
                        }
                    } else {
                        $obj_targetPageCollection = PageModel::findById($objCorrespondingPages->row()['id']);
                        if ($obj_targetPageCollection->current()->type === 'regular') {
                            $href = $obj_targetPageCollection->current()->getFrontendUrl($queryString) . ($secondQueryString ? '?' . $secondQueryString : '');

                            $targetRootDns = $this->getRootPageDns($pageDetails->rootId);
                            if ($targetRootDns && $targetRootDns != $currentDomain) {
                                $href = $this->buildAbsoluteUrlFromRootId($pageDetails->rootId, $href);
                            }

                            $languagesForCurrentDomain[$pageDetails->language]['href'] = $href;
                        }
                    }
				}
			}
		}

		if (isset($GLOBALS['LS_LANGUAGESELECTOR_HOOKS']['modifyLanguageLinks']) && is_array($GLOBALS['LS_LANGUAGESELECTOR_HOOKS']['modifyLanguageLinks'])) {
			foreach ($GLOBALS['LS_LANGUAGESELECTOR_HOOKS']['modifyLanguageLinks'] as $mccb) {
				$objMccb = System::importStatic($mccb[0]);
				$languagesForCurrentDomain = $objMccb->{$mccb[1]}($languagesForCurrentDomain, $objPage->language);
			}
		}

        self::$cache_getCorrespondingLanguagesForCurrentRootPage[$pageID] = $languagesForCurrentDomain;
		return $languagesForCurrentDomain;
	}

	/**
	 * Prüft, ob eine Root-Page zur angegebenen Language Group gehört.
	 *
	 * @param int $rootPageId ID der zu prüfenden Root-Page
	 * @param int $masterRootId ID der Master-Root-Page der Gruppe
	 * @return bool
	 */
	public function belongsToSameLanguageGroup(int $rootPageId, int $masterRootId): bool
	{
		if ($rootPageId === $masterRootId) {
			return true;
		}

		$objRoot = Database::getInstance()->prepare(
			"SELECT ls_cnc_languageSelector_languageGroup FROM `tl_page` WHERE `id` = ?"
		)->limit(1)->execute($rootPageId);

		if (!$objRoot->numRows) {
			return false;
		}

		return (int) $objRoot->ls_cnc_languageSelector_languageGroup === $masterRootId;
	}

	/**
	 * Ermittelt den `dns`-Wert einer Root-Page.
	 *
	 * @param int $rootPageId ID der Root-Page
	 * @return string|null `dns`-Wert oder null
	 */
	public function getRootPageDns(int $rootPageId): ?string
	{
		$objRoot = Database::getInstance()->prepare(
			"SELECT dns, rootUseSSL FROM `tl_page` WHERE `id` = ?"
		)->limit(1)->execute($rootPageId);

		if (!$objRoot->numRows) {
			return null;
		}

		return $objRoot->dns;
	}

	/**
	 * Generiert eine absolute URL aus einer Root-Page-Zeile und einem relativen Pfad.
	 *
	 * @param object $rootPageRow Datenbankzeile der Root-Page (mit `dns`, `rootUseSSL`)
	 * @param string $relativePath Relativer Pfad (z. B. aus `getFrontendUrl()`)
	 * @return string Absolute URL
	 */
	public function buildAbsoluteUrl(object $rootPageRow, string $relativePath): string
	{
		$protocol = $rootPageRow->rootUseSSL ? 'https://' : 'http://';
		$domain = $rootPageRow->dns;

		$relativePath = ltrim($relativePath, '/');

		return $protocol . $domain . '/' . $relativePath;
	}

	/**
	 * Generiert eine absolute URL anhand einer Root-Page-ID und eines relativen Pfads.
	 *
	 * @param int $rootPageId ID der Root-Page
	 * @param string $relativePath Relativer Pfad
	 * @return string Absolute URL oder unveränderter Pfad bei Fehler
	 */
	public function buildAbsoluteUrlFromRootId(int $rootPageId, string $relativePath): string
	{
		$objRoot = Database::getInstance()->prepare(
			"SELECT dns, rootUseSSL FROM `tl_page` WHERE `id` = ?"
		)->limit(1)->execute($rootPageId);

		if (!$objRoot->numRows || !$objRoot->dns) {
			return $relativePath;
		}

		return $this->buildAbsoluteUrl($objRoot, $relativePath);
	}

	/**
	 * Liefert zu einer `pageID` die `pageID` der korrespondierenden Hauptsprachseite
	 * bzw. gibt die `pageID` zurück, sofern es sich bereits um die Hauptsprachseite handelt.
	 *
	 * Hauptsprachseite (Root-Page ist Master oder Fallback ohne Language Group) => eigene ID.
	 * Nebenseite mit Zuordnung in derselben Gruppe => zugeordnete ID.
	 * Keine Zuordnung oder `false` => `0`.
	 */
	public function getMainlanguagePageIDForPageID($pageID = false) {
		$mainLanguagePageID = 0;
		if (!$pageID) {
			return $mainLanguagePageID;
		}

        if (array_key_exists($pageID, self::$cache_getMainlanguagePageIDForPageID)) {
            return self::$cache_getMainlanguagePageIDForPageID[$pageID];
        }

		$objPageDetails = PageModel::findWithDetails($pageID);
		$objRootPage = Database::getInstance()->prepare("SELECT * FROM `tl_page` WHERE `id` = ?")
							->limit(1)
							->execute($objPageDetails->rootId);

		$languageGroupId = (int) $objRootPage->ls_cnc_languageSelector_languageGroup;
		$useLanguageGroup = false;
		$masterRootId = 0;
		$isMaster = false;

		if ($languageGroupId) {
			$useLanguageGroup = true;
			$masterRootId = $languageGroupId;
		} else {
			$objSlavesCheck = Database::getInstance()->prepare(
				"SELECT id FROM `tl_page` WHERE `ls_cnc_languageSelector_languageGroup` = ? LIMIT 1"
			)->execute($objRootPage->id);

			if ($objSlavesCheck->numRows) {
				$useLanguageGroup = true;
				$isMaster = true;
				$masterRootId = (int) $objRootPage->id;
			}
		}

		$isMainLanguagePage = $isMaster || (!$useLanguageGroup && $objRootPage->fallback);

		if ($isMainLanguagePage) {
			$mainLanguagePageID = $pageID;
		} else {
			$correspondingId = (int) $objPageDetails->ls_cnc_languageSelector_correspondingMainLanguagePage;

			if ($correspondingId) {
				$obj_correspondingPage = PageModel::findWithDetails($correspondingId);

				if ($obj_correspondingPage !== null) {
					if ($useLanguageGroup) {
						if ($this->belongsToSameLanguageGroup((int) $obj_correspondingPage->rootId, $masterRootId)) {
							$mainLanguagePageID = $correspondingId;
						}
					} else {
						if ($obj_correspondingPage->domain == $objPageDetails->domain) {
							$mainLanguagePageID = $correspondingId;
						}
					}
				}
			}
		}

        self::$cache_getMainlanguagePageIDForPageID[$pageID] = $mainLanguagePageID;
		return $mainLanguagePageID;
	}
}
