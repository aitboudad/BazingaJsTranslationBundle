<?php

namespace Bazinga\Bundle\JsTranslationBundle\Translator;

use Symfony\Bundle\FrameworkBundle\Translation\Translator;

class BazingaJsTranslator extends Translator
{
    public function getDefaultDomain()
    {
        return 'messages';
    }

    /**
     * Gets the catalogue by locale.
     */
    public function getCatalogue($locale = null)
    {
        if (null === $locale) {
            $locale = $this->getLocale();
        } else {
            $this->assertValidLocale($locale);
        }

        if (!isset($this->catalogues[$locale])) {
            $this->loadCatalogue($locale);
        }

        return $this->catalogues[$locale];
    }
}
