<?php

namespace Bazinga\Bundle\JsTranslationBundle\Dumper;

use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Filesystem\Filesystem;
use Bazinga\Bundle\JsTranslationBundle\Translator\BazingaJsTranslator;

/**
 * @author Adrien Russo <adrien.russo.qc@gmail.com>
 */
class TranslationDumper
{
    /**
     * @var EngineInterface
     */
    private $engine;

    /**
     * @var BazingaJsTranslator
     */
    private $translator;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var array List of locales translations to dump
     */
    private $activeLocales;

    /**
     * @var array List of domains translations to dump
     */
    private $activeDomains;

    /**
     * @param EngineInterface     $engine         The engine.
     * @param BazingaJsTranslator $translator     The translation translator.
     * @param RouterInterface     $router         The router.
     * @param FileSystem          $filesystem     The file system.
     * @param array               $activeLocales.
     * @param array               $activeDomains.
     */
    public function __construct(
        EngineInterface $engine,
        BazingaJsTranslator $translator,
        RouterInterface $router,
        Filesystem $filesystem,
        array $activeLocales = array(),
        array $activeDomains = array()
    ) {
        $this->engine = $engine;
        $this->translator = $translator;
        $this->router = $router;
        $this->filesystem = $filesystem;

        // Add fallback locale to active locales if missing
        $this->activeLocales = array_unique(array_merge($activeLocales, $translator->getFallbackLocales()));
        $this->activeDomains = $activeDomains;
    }

    /**
     * Get array of active locales.
     */
    public function getActiveLocales()
    {
        return $this->activeLocales;
    }

    /**
     * Get array of active locales.
     */
    public function getActiveDomains()
    {
        return $this->activeDomains;
    }

    /**
     * Dump all translation files.
     *
     * @param string $target Target directory.
     */
    public function dump($target = 'web/js')
    {
        $route = $this->router->getRouteCollection()->get('bazinga_jstranslation_js');
        $requirements = $route->getRequirements();
        $formats = explode('|', $requirements['_format']);

        $parts = array_filter(explode('/', $route->getPattern()));
        $this->filesystem->remove($target.'/'.current($parts));

        $this->dumpConfig($route, $formats, $target);
        $this->dumpTranslations($route, $formats, $target);
    }

    private function dumpConfig($route, array $formats, $target)
    {
        foreach ($formats as $format) {
            $file = sprintf('%s/%s',
                $target,
                strtr($route->getPattern(), array(
                    '{domain}' => 'config',
                    '{_format}' => $format,
                ))
            );

            $this->filesystem->mkdir(dirname($file));

            if (file_exists($file)) {
                $this->filesystem->remove($file);
            }

            file_put_contents(
                $file,
                $this->engine->render('BazingaJsTranslationBundle::config.'.$format.'.twig', array(
                    'fallbacks' => $this->translator->getFallbackLocales(),
                    'defaultDomain' => $this->translator->getDefaultDomain(),
                ))
            );
        }
    }

    private function dumpTranslations($route, array $formats, $target)
    {
        foreach ($this->getTranslations() as $locale => $domains) {
            foreach ($domains as $domain => $translations) {
                foreach ($formats as $format) {
                    $content = $this->engine->render('BazingaJsTranslationBundle::getTranslations.'.$format.'.twig', array(
                        'translations' => array($locale => array(
                            $domain => $translations,
                        )),
                        'include_config' => false,
                    ));

                    $file = sprintf('%s/%s',
                        $target,
                        strtr($route->getPattern(), array(
                            '{domain}' => sprintf('%s/%s', $domain, $locale),
                            '{_format}' => $format,
                        ))
                    );

                    $this->filesystem->mkdir(dirname($file));

                    if (file_exists($file)) {
                        $this->filesystem->remove($file);
                    }

                    file_put_contents($file, $content);
                }
            }
        }
    }

    /**
     * @return array
     */
    private function getTranslations()
    {
        $translations = array();
        $activeLocales = $this->activeLocales;
        $activeDomains = $this->activeDomains;

        foreach ($activeLocales as $locale) {
            $translations[$locale] = array();

            $catalogue = $this->translator->getCatalogue($locale);
            foreach ($activeDomains as $domain) {
                $messages = $catalogue->all($domain);
                if (!empty($messages)) {
                    $translations[$locale][$domain] = $messages;
                }
            }
        }

        return $translations;
    }
}
