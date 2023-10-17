<?php

namespace LeadingSystems\LanguageSelector;

use Contao\System;

class ModuleLanguageSelector extends \Module {
    protected $arrPages = array();

    protected $strTemplate = 'mod_ls_cnc_languageSelector_selector';

    public function generate()
    {
        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request))
        {
            $objTemplate = new \BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### LEADING SYSTEMS LANGUAGE SELECTOR ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'typolight/main.php?do=modules&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        return parent::generate();
    }

    protected function compile()
    {
        global $objPage;
        $obj_controller = new LsController();
        $this->Template->currentLanguage = $objPage->language;
        $this->Template->correspondingPages = $obj_controller->getCorrespondingLanguagesForCurrentRootPage();
    }
}
