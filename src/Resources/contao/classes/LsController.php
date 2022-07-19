<?php

namespace LeadingSystems\LanguageSelector;

class LsController {
	public function getCorrespondingLanguagesForCurrentRootPage($pageID = false) {
		if (!$pageID) {
			global $objPage;
		} else {
			$objPage = \PageModel::findWithDetails($pageID);
		}


		/*
		 * Ermitteln der Domain der aktuellen Root-Page
		 */
		if ($objPage->rootId) {
			$objRootPage = \Database::getInstance()->prepare("SELECT * FROM `tl_page` WHERE `id` = ?")
											->limit(1)
											->execute($objPage->rootId);
		} else {
			$objRootPage = $objPage;
		}

		$currentDomain = $objRootPage->dns;

		/*
		 * Ermitteln aller Root-Pages mit derselben Domain
		 */
		$objRootPagesWithSameDomain = \Database::getInstance()->prepare("SELECT * FROM `tl_page` WHERE `type` = 'root' AND `dns` = ? AND `published` = 1 ORDER BY `sorting`")
														->execute($currentDomain);

		/*
		 * Ermitteln aller Root-Page-Sprachen f�r die aktuelle Domain.
		 * Dem Sprach-Array werden die zu den jeweiligen Sprachen passenden Verlinkungen hinterlegt. Beim Erstellen des Arrays
		 * werden hier quasi als "Fallback" die Verlinkungen der Root-Pages hinterlegt. Können passendere korrespondierende
		 * Seiten f�r eine Sprache ermittelt werden, so finden diese Verwendung, ist das aber nicht m�glich, so kommt eben
		 * die hier schon eingetragene Root-Page zum Einsatz.
		 */
		$languagesForCurrentDomain = array();
		while ($objRootPagesWithSameDomain->next()) {
		    /*
		     * Load the languages array in every language so that we can show each language name in that language
		     */
            $obj_pageModel = \PageModel::findByAlias($objPage->language != $objRootPagesWithSameDomain->language ? $objRootPagesWithSameDomain->row()['alias'] : $objPage->row()['alias']);

            \System::loadLanguageFile('languages', $objRootPagesWithSameDomain->language, true);
			if (!in_array($objRootPagesWithSameDomain->language, $languagesForCurrentDomain)) {
				$languagesForCurrentDomain[$objRootPagesWithSameDomain->language] = array(
					'alias' => $objPage->language != $objRootPagesWithSameDomain->language ? $objRootPagesWithSameDomain->alias : $objPage->alias,
					'id' => $objPage->language != $objRootPagesWithSameDomain->language ? $objRootPagesWithSameDomain->id : $objPage->id,
					'href' => $obj_pageModel->current()->getFrontendUrl(),
					'languageTitle' => $GLOBALS['TL_LANG']['LNG'][$objRootPagesWithSameDomain->language]
				);
			}
		}
		/*
		 * Just for safety, load the languages array in the current page language
		 */
		\System::loadLanguageFile('languages', $objPage->language, true);

		/*
		 * Ermitteln der korrespondierenden Hauptsprach-Seiten-ID. Ist die Seite selbst Hauptsprachseite, so ist das
		 * die eigene ID.
		 */
		$mainLanguageID = $objRootPage->fallback ? $objPage->id : $objPage->ls_cnc_languageSelector_correspondingMainLanguagePage;

		if ($mainLanguageID) {
			/*
			 * Ermitteln aller Seiten, denen die entsprechende Hauptsprach-Seiten-ID als korrespondierende Seite hinterlegt ist.
			 */
			$objCorrespondingPages = \Database::getInstance()->prepare("SELECT * FROM `tl_page` WHERE (`ls_cnc_languageSelector_correspondingMainLanguagePage` = ? OR `id` = ?) AND `published` = 1")
													->execute($mainLanguageID, $mainLanguageID);

			/*
			 * Hinterlegen der Sprach-Seiten in das Sprach-Array
			 */
			while ($objCorrespondingPages->next()) {
				$pageDetails = \PageModel::findWithDetails($objCorrespondingPages->id);
				if ($pageDetails->domain != $currentDomain) {
					continue;
				}
				if (isset($languagesForCurrentDomain[$pageDetails->language])) {
					$languagesForCurrentDomain[$pageDetails->language]['alias'] = $objCorrespondingPages->alias;
					$languagesForCurrentDomain[$pageDetails->language]['id'] = $objCorrespondingPages->id;

					/*
					 * create query string
					 */
					$queryString = '';
					$secondQueryString = '';
					if (isset($_GET) && is_array($_GET)) {
						/*
						 * Some Get-Parameters may cause problems and therfore have to be excluded. The parameter "articles" e.g. should not be used in the url for
						 * another language because it always is a reference to a unique article alias which does only exist in the current language page.
						 * If an article is opened and this parameter would be used in the link to another language an error (item does not exist)  would occur.
						 * Perhaps there are more parameters which cause problems and maybe there are some situations in which even these problematic parameters
						 * should not be excluded so maybe it would be a good idea to allow the website admin to define the parameters to exclude in the contao settings.
						 */
						$arrExcludedGetParameters = array('articles','auto_item','language');

						foreach ($_GET as $k => $v) {
							if (in_array($k, $arrExcludedGetParameters)) {
								continue;
							}

							if (!preg_match('/'.preg_quote($k, '/').'=/', \Environment::get('request'))) {
								$queryString .= '/'.$k.'/'.\Input::get($k);
							} else {
								$secondQueryString .= ($secondQueryString ? '&amp;' : '').$k.'='.\Input::get($k);
							}
						}
					}

                    if(\Input::get('auto_item')) {
                        $obj_targetPageCollection = \PageModel::findByAlias($pageDetails->parentAlias);
                        $languagesForCurrentDomain[$pageDetails->language]['href'] = $obj_targetPageCollection->current()->getFrontendUrl();
                    } else {
                        $obj_targetPageCollection = \PageModel::findByAlias($objCorrespondingPages->row()['alias']);
                        $languagesForCurrentDomain[$pageDetails->language]['href'] = $obj_targetPageCollection->current()->getFrontendUrl($queryString, $pageDetails->language).($secondQueryString ? '?'.$secondQueryString : '');
                    }
				}
			}
		}

		if (isset($GLOBALS['LS_LANGUAGESELECTOR_HOOKS']['modifyLanguageLinks']) && is_array($GLOBALS['LS_LANGUAGESELECTOR_HOOKS']['modifyLanguageLinks'])) {
			foreach ($GLOBALS['LS_LANGUAGESELECTOR_HOOKS']['modifyLanguageLinks'] as $mccb) {
				$objMccb = \System::importStatic($mccb[0]);
				$languagesForCurrentDomain = $objMccb->{$mccb[1]}($languagesForCurrentDomain, $objPage->language);
			}
		}

		return $languagesForCurrentDomain;
	}

	/*
	 * Diese Funktion liefert zu einer pageID die pageID der korrespondierenden Hauptsprachseite
	 * bzw. gibt die pageID wieder zurück, sofern es sich dabei bereits um die Hauptsprachseite handelt.
	 */
	public function getMainlanguagePageIDForPageID($pageID = false) {
		$mainLanguagePageID = 0;
		if (!$pageID) {
			return $mainLanguagePageID;
		}

		$objPageDetails = \PageModel::findWithDetails($pageID);
		$objRootPage = \Database::getInstance()->prepare("SELECT * FROM `tl_page` WHERE `id` = ?")
							->limit(1)
							->execute($objPageDetails->rootId);

		/*
		 * Ermitteln der korrespondierenden Hauptsprach-Seiten-ID. Ist die Seite selbst Hauptsprachseite, so ist das
		 * die eigene ID.
		 */
		if ($objRootPage->fallback) {
			$mainLanguagePageID = $pageID;
		} else {
			$obj_correspondingMainLanguagePageDetails = \PageModel::findWithDetails($objPageDetails->ls_cnc_languageSelector_correspondingMainLanguagePage);
			if ($obj_correspondingMainLanguagePageDetails->domain == $objPageDetails->domain) {
				$mainLanguagePageID = $objPageDetails->ls_cnc_languageSelector_correspondingMainLanguagePage;
			}
		}

		return $mainLanguagePageID;
	}
}
