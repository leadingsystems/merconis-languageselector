<?php

use Contao\Backend;
use Contao\Input;
use Contao\PageModel;

$GLOBALS['TL_DCA']['tl_page']['config']['onload_callback'][] = array('tl_page_ls_cnc_languageSelector', 'insertSelectorForCorrespondingMainLanguagePage');
$GLOBALS['TL_DCA']['tl_page']['config']['onload_callback'][] = array('tl_page_ls_cnc_languageSelector', 'handleLanguageGroupField');

$GLOBALS['TL_DCA']['tl_page']['list']['label']['label_callback'] = array('tl_page_ls_cnc_languageSelector', 'showMessageIfNoCorrespondingPageSelected');

$GLOBALS['TL_DCA']['tl_page']['fields']['ls_cnc_languageSelector_correspondingMainLanguagePage'] = array
(
    'label'                   => &$GLOBALS['TL_LANG']['tl_page']['ls_cnc_languageSelector_correspondingMainLanguagePage'],
    'exclude'                 => true,
    'inputType'               => 'pageTree',
    'eval'                    => array('fieldType' => 'radio', 'tl_class' => 'w50'),
    'sql'                     => "int(10) unsigned NOT NULL default '0'"
);

$GLOBALS['TL_DCA']['tl_page']['fields']['ls_cnc_languageSelector_languageGroup'] = array
(
    'label'            => &$GLOBALS['TL_LANG']['tl_page']['ls_cnc_languageSelector_languageGroup'],
    'exclude'          => true,
    'inputType'        => 'select',
    'options_callback' => array('tl_page_ls_cnc_languageSelector', 'getLanguageGroupOptions'),
    'eval'             => array(
        'includeBlankOption' => true,
        'tl_class'           => 'w50'
    ),
    'sql'              => "int(10) unsigned NOT NULL default '0'"
);

class tl_page_ls_cnc_languageSelector extends Backend {

    /**
     * Fügt das Language-Group-Feld auf Fallback-Root-Pages in die Palette ein
     * und sperrt es, wenn die Seite bereits Master ist.
     */
    public function handleLanguageGroupField($dc) {
        if (Input::get('act') != 'edit' || !$dc->id) {
            return;
        }

        $objPage = $this->Database->prepare(
            "SELECT id, type, fallback FROM tl_page WHERE id = ?"
        )->limit(1)->execute($dc->id);

        if (!$objPage->numRows || $objPage->type != 'root' || $objPage->fallback != 1) {
            return;
        }

        $GLOBALS['TL_DCA']['tl_page']['palettes']['root'] = preg_replace(
            '@([,;]language)([,;])@',
            '$1,ls_cnc_languageSelector_languageGroup$2',
            $GLOBALS['TL_DCA']['tl_page']['palettes']['root']
        );

        $objSlaves = $this->Database->prepare(
            "SELECT id, title, language FROM tl_page WHERE ls_cnc_languageSelector_languageGroup = ?"
        )->execute($dc->id);

        if ($objSlaves->numRows) {
            $GLOBALS['TL_DCA']['tl_page']['fields']['ls_cnc_languageSelector_languageGroup']['eval']['disabled'] = true;

            $slaveLabels = array();
            while ($objSlaves->next()) {
                $slaveLabels[] = '(' . $objSlaves->language . ') ' . $objSlaves->title;
            }

            $GLOBALS['TL_DCA']['tl_page']['fields']['ls_cnc_languageSelector_languageGroup']['label'][1] = sprintf(
                $GLOBALS['TL_LANG']['tl_page']['languageGroupMasterInfo'],
                implode(', ', $slaveLabels)
            );
        }
    }

    /**
     * Gibt alle Root-Pages zurück, die als Master einer Language Group wählbar sind.
     */
    public function getLanguageGroupOptions($dc) {
        return $this->buildLanguageGroupOptions($dc->id);
    }

    /**
     * Filterlogik für die Language-Group-Auswahl, extrahiert für Testbarkeit.
     *
     * @param int $currentPageId ID der aktuell bearbeiteten Seite
     * @return array<int, string> ID => Label
     */
    public function buildLanguageGroupOptions($currentPageId) {
        $options = array();

        $objRootPages = $this->Database->prepare(
            "SELECT id, title, language FROM tl_page"
            . " WHERE type = 'root'"
            . " AND ls_cnc_languageSelector_languageGroup = 0"
            . " AND id != ?"
        )->execute($currentPageId);

        while ($objRootPages->next()) {
            $options[$objRootPages->id] = '[' . $objRootPages->language . '] ' . $objRootPages->title;
        }

        return $options;
    }

    public function insertSelectorForCorrespondingMainLanguagePage($dc) {
        if (Input::get('act') == 'editAll') {
            $GLOBALS['TL_DCA']['tl_page']['fields']['title']['eval']['tl_class'] = 'w50';
            $GLOBALS['TL_DCA']['tl_page']['fields']['alias']['eval']['tl_class'] = 'clr w50';
            $GLOBALS['TL_DCA']['tl_page']['palettes']['regular'] = preg_replace(
                '@([,|;]title)([,|;])@',
                '$1,ls_cnc_languageSelector_correspondingMainLanguagePage$2',
                $GLOBALS['TL_DCA']['tl_page']['palettes']['regular']
            );
            return;
        }

        if (Input::get('act') == 'edit') {
            $objPage = PageModel::findWithDetails($dc->id);

            if ($objPage->type == 'regular') {
                $objRootPage = $this->Database->prepare(
                    "SELECT * FROM tl_page WHERE id = ? AND fallback != 1"
                )->limit(1)->execute($objPage->rootId);

                $objSlaveRoot = $this->Database->prepare(
                    "SELECT * FROM tl_page WHERE id = ? AND ls_cnc_languageSelector_languageGroup != 0"
                )->limit(1)->execute($objPage->rootId);

                if ($objRootPage->numRows || $objSlaveRoot->numRows) {
                    $GLOBALS['TL_DCA']['tl_page']['fields']['title']['eval']['tl_class'] = 'w50';
                    $GLOBALS['TL_DCA']['tl_page']['fields']['alias']['eval']['tl_class'] = 'clr w50';
                    $GLOBALS['TL_DCA']['tl_page']['palettes']['regular'] = preg_replace(
                        '@([,|;]title)([,|;])@',
                        '$1,ls_cnc_languageSelector_correspondingMainLanguagePage$2',
                        $GLOBALS['TL_DCA']['tl_page']['palettes']['regular']
                    );

                    $intMasterRootId = $this->getMasterRootPageId($objPage->rootId);

                    if ($intMasterRootId) {
                        $GLOBALS['TL_DCA']['tl_page']['fields']['ls_cnc_languageSelector_correspondingMainLanguagePage']['eval']['rootNodes'] = array($intMasterRootId);
                    }
                }
            }
        }
    }

    /**
     * Ermittelt die Master-Root-Page-ID für eine gegebene Root-Page.
     *
     * @param int $rootPageId ID der Root-Page
     * @return int Master-Root-Page-ID oder 0
     */
    public function getMasterRootPageId($rootPageId) {
        $objRootPage = $this->Database->prepare(
            "SELECT id, dns, ls_cnc_languageSelector_languageGroup FROM tl_page WHERE id = ?"
        )->limit(1)->execute($rootPageId);

        if (!$objRootPage->numRows) {
            return 0;
        }

        if ($objRootPage->ls_cnc_languageSelector_languageGroup != 0) {
            return (int) $objRootPage->ls_cnc_languageSelector_languageGroup;
        }

        $objFallbackRoot = $this->Database->prepare(
            "SELECT id FROM tl_page WHERE type = 'root' AND fallback = 1 AND dns = ?"
        )->limit(1)->execute($objRootPage->dns);

        if ($objFallbackRoot->numRows) {
            return (int) $objFallbackRoot->id;
        }

        return 0;
    }

    public function showMessageIfNoCorrespondingPageSelected($row, $label, $dc, $imageAttribute, $blnReturnImage = false) {
        $obj_tl_page = new \tl_page();
        $label = $obj_tl_page->addIcon($row, $label, $dc, $imageAttribute, $blnReturnImage);

        if ($row['type'] == 'root') {
            $objSlaves = $this->Database->prepare(
                "SELECT id, title, language FROM tl_page WHERE ls_cnc_languageSelector_languageGroup = ?"
            )->execute($row['id']);

            if ($objSlaves->numRows) {
                $slaveData = array();
                while ($objSlaves->next()) {
                    $slaveData[] = array('language' => $objSlaves->language, 'title' => $objSlaves->title);
                }
                $label .= ' <span style="color:#b3b3b3; padding-left:3px;">'
                    . $this->formatLanguageGroupMasterLabel($slaveData) . '</span>';
            } elseif (!empty($row['ls_cnc_languageSelector_languageGroup'])) {
                $objMaster = $this->Database->prepare(
                    "SELECT id, title, language FROM tl_page WHERE id = ?"
                )->limit(1)->execute($row['ls_cnc_languageSelector_languageGroup']);

                if ($objMaster->numRows) {
                    $label .= ' <span style="color:#b3b3b3; padding-left:3px;">'
                        . $this->formatLanguageGroupSlaveLabel($objMaster->language, $objMaster->title) . '</span>';
                }
            }
        }

        if (!$row['ls_cnc_languageSelector_correspondingMainLanguagePage']) {
            $objPage = PageModel::findWithDetails($row['id']);

            if ($objPage !== null && $objPage->type == 'regular') {
                $objRootPage = $this->Database->prepare(
                    "SELECT * FROM tl_page WHERE id = ? AND fallback != 1"
                )->limit(1)->execute($objPage->rootId);

                $objSlaveRoot = $this->Database->prepare(
                    "SELECT * FROM tl_page WHERE id = ? AND ls_cnc_languageSelector_languageGroup != 0"
                )->limit(1)->execute($objPage->rootId);

                if ($objRootPage->numRows || $objSlaveRoot->numRows) {
                    $label .= '<span style="color:#b3b3b3; padding-left:3px;">['
                        . $GLOBALS['TL_LANG']['MSC']['noMainLanguage'] . ']</span>';
                }
            }
        }

        return $label;
    }

    /**
     * Formatiert das Language-Group-Label für eine Master-Root-Page.
     *
     * @param array<int, array{language: string, title: string}> $slaves
     * @return string Formatiertes Label oder leerer String
     */
    public function formatLanguageGroupMasterLabel(array $slaves): string
    {
        if (empty($slaves)) {
            return '';
        }

        $labels = array();
        foreach ($slaves as $slave) {
            $labels[] = '(' . $slave['language'] . ') ' . $slave['title'];
        }

        return sprintf(
            $GLOBALS['TL_LANG']['tl_page']['languageGroupLabelMaster'],
            implode(', ', $labels)
        );
    }

    /**
     * Formatiert das Language-Group-Label für eine Slave-Root-Page.
     *
     * @param string $masterLanguage Sprachkürzel der Master-Root-Page
     * @param string $masterTitle Titel der Master-Root-Page
     * @return string Formatiertes Label
     */
    public function formatLanguageGroupSlaveLabel(string $masterLanguage, string $masterTitle): string
    {
        return sprintf(
            $GLOBALS['TL_LANG']['tl_page']['languageGroupLabelSlave'],
            '(' . $masterLanguage . ') ' . $masterTitle
        );
    }
}
