<?php

/*
 * Einf�gen eines Auswahlfelds zur Auswahl der korrespondierenden Seite der Hauptsprache, sofern die aktuelle Seite
 * ein Child einer Nicht-Hauptsprache ist. Hierbei werden alle Seiten zur Auswahl angeboten, f�r die gilt:
 * 		1. Root-Page als Sprachen-Fallback gekennzeichnet ist
 * 		2. Root-Page hat selbe Domain hinterlegt wie die Root-Page der aktuelle bearbeiteten Seite
 */

use Contao\Backend;
use Contao\PageModel;

$GLOBALS['TL_DCA']['tl_page']['config']['onload_callback'][] = array('tl_page_ls_cnc_languageSelector','insertSelectorForCorrespondingMainLanguagePage');

$GLOBALS['TL_DCA']['tl_page']['list']['label']['label_callback'] = array('tl_page_ls_cnc_languageSelector', 'showMessageIfNoCorrespondingPageSelected');

$GLOBALS['TL_DCA']['tl_page']['fields']['ls_cnc_languageSelector_correspondingMainLanguagePage'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_page']['ls_cnc_languageSelector_correspondingMainLanguagePage'],
    'exclude'                 => true,
    'inputType'               => 'select',
    'options_callback'        => array('tl_page_ls_cnc_languageSelector', 'getCorrespondingMainLanguagePages'),
    'eval'                    => array('tl_class' => 'w50'),
    'sql'                     => "int(10) unsigned NOT NULL default '0'"
);

class tl_page_ls_cnc_languageSelector extends Backend {
    public $arrPages = array();

    public function insertSelectorForCorrespondingMainLanguagePage($dc) {
        if ($this->Input->get('act') == "edit") {
            /*
             * Im einfachen edit-Modus wird gepr�ft, ob die aktuell bearbeitete Seite Child einer Root-Page ist, die selbst kein Sprachen-Fallback ist.
             * Ist dies der Fall, so wird das Auswahlfeld ausgegeben
             */
            $objPage = PageModel::findWithDetails($dc->id);

            if ($objPage->type == 'regular') {
                $objRootPage = $this->Database->prepare("SELECT * FROM `tl_page` WHERE `id` = ? AND `fallback` != 1")
                                                ->limit(1)
                                                ->execute($objPage->rootId);
                if($objRootPage->numRows) {
                    $GLOBALS['TL_DCA']['tl_page']['fields']['title']['eval']['tl_class'] = 'w50';
                    $GLOBALS['TL_DCA']['tl_page']['fields']['alias']['eval']['tl_class'] = 'clr w50';
                    $GLOBALS['TL_DCA']['tl_page']['palettes']['regular'] = preg_replace('@([,|;]title)([,|;])@','$1,ls_cnc_languageSelector_correspondingMainLanguagePage$2', $GLOBALS['TL_DCA']['tl_page']['palettes']['regular']);
                }
            }
        } else if($this->Input->get('act') == "editAll") {
            /*
             * Im editAll-Modus wird das Auswahlfeld auf jeden Fall ausgegeben
             */
            $GLOBALS['TL_DCA']['tl_page']['fields']['title']['eval']['tl_class'] = 'w50';
            $GLOBALS['TL_DCA']['tl_page']['fields']['alias']['eval']['tl_class'] = 'clr w50';
            $GLOBALS['TL_DCA']['tl_page']['palettes']['regular'] = preg_replace('@([,|;]title)([,|;])@','$1,ls_cnc_languageSelector_correspondingMainLanguagePage$2', $GLOBALS['TL_DCA']['tl_page']['palettes']['regular']);
        }
    }

    /*
     * Es werden alle Seiten zur Auswahl zur�ckgegeben, die Child einer Root-Page sind, welche die Hauptsprache der Root-Page
     * der aktuell bearbeiteten Seite darstellt. Sprich: Ist die aktuell bearbeitete Seite eine Seite, deren Root-Page den
     * fremdsprachigen Seitenbaum kennzeichnet, so werden alle Seiten zur Auswahl angeboten, die der Root-Page untergeordnet sind,
     * welche den hauptsprachigen Seitenbaum kennzeichnet (Sprachen-Fallback = 1). Welche Sprachen-Fallback-Root-Page mit der Root-Page
     * der aktuell bearbeiteten Seite korrespondiert, wird anhand des gleichen Domaineintrags (dns) ermittelt.
     */
    public function getCorrespondingMainLanguagePages($dc) {
        $this->arrPages[0] = $GLOBALS['TL_LANG']['tl_page']['noCorrespondingPageSelected'];
        $objPage = PageModel::findWithDetails($dc->id);

        $objRootPage = $this->Database->prepare("SELECT * FROM `tl_page` WHERE `id`= ?")
                                        ->limit(1)
                                        ->execute($objPage->rootId);

        $objCorrespondingRootPage = $this->Database->prepare("SELECT * FROM `tl_page` WHERE `fallback` = 1 AND `dns` = ?")
                                                    ->limit(1)
                                                    ->execute($objRootPage->dns);

        if ($objCorrespondingRootPage->numRows) {
            $this->createPageOptionsArray($objCorrespondingRootPage->id, 0);
        }

        return $this->arrPages;
    }

    protected function createPageOptionsArray ($intId = 0, $level = -1) {
        $objPages = $this->Database->prepare("SELECT `id`, `title` FROM `tl_page` WHERE `pid` = ? AND (`type`  = 'regular' OR `type` = 'redirect' OR `type` = 'forward') ORDER BY sorting")
                                   ->execute($intId);

        if ($objPages->numRows < 1) {
            return;
        }

        ++$level;

        while ($objPages->next()) {
            $this->arrPages[$objPages->id] = str_repeat("&nbsp;", (3 * $level)) . $objPages->title;

            $this->createPageOptionsArray($objPages->id, $level);
        }
    }

    public function showMessageIfNoCorrespondingPageSelected($row, $label, $dc, $imageAttribute, $blnReturnImage = false) {
        $obj_tl_page = new tl_page();
        $label = $obj_tl_page->addIcon($row, $label, $dc, $imageAttribute, $blnReturnImage);

        // Wenn keine korrespondierende Seite ausgewählt ist
        if (!$row['ls_cnc_languageSelector_correspondingMainLanguagePage']) {
            $objPage = PageModel::findWithDetails($row['id']);

            // Wenn es sich um eine regular-Seite handelt
            if ($objPage->type == 'regular') {
                $objRootPage = $this->Database->prepare("SELECT * FROM `tl_page` WHERE `id` = ? AND `fallback` != 1")
                                                ->limit(1)
                                                ->execute($objPage->rootId);
                // Wenn es eine Root-Page für die aktuelle Seite gibt, die kein Fallback ist
                // Sprich: Wenn es sich bei der aktuellen Seite nicht um eine Hauptsprach-Seite handelt
                //
                // ===> nur dann wird ein Hinweis auf die fehlende Zuordnung ausgegeben
                if($objRootPage->numRows) {
                    $label .= '<span style="color:#b3b3b3; padding-left:3px;">[' . $GLOBALS['TL_LANG']['MSC']['noMainLanguage'] . ']</span>';
                }
            }
        }

        return $label;
    }
}
