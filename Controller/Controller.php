<?php

namespace Bazinga\Bundle\JsTranslationBundle\Controller;

use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Bazinga\Bundle\JsTranslationBundle\Translator\BazingaJsTranslator;

/**
 * @author William DURAND <william.durand1@gmail.com>
 */
class Controller
{
    /**
     * @var BazingaJsTranslator
     */
    private $translator;

    /**
     * @var EngineInterface
     */
    private $engine;

    /**
     * @var int
     */
    private $httpCacheTime;

    /**
     * @param BazingaJsTranslator $translator    The translator.
     * @param EngineInterface     $engine        The engine.
     * @param int                 $httpCacheTime
     */
    public function __construct(
        BazingaJsTranslator $translator,
        EngineInterface $engine,
        $httpCacheTime = 86400
    ) {
        $this->translator = $translator;
        $this->engine = $engine;
        $this->httpCacheTime = $httpCacheTime;
    }

    public function getTranslationsAction(Request $request, $domain, $_format)
    {
        $locales = $this->getLocales($request);

        if (0 === count($locales)) {
            throw new NotFoundHttpException();
        }

        $translations = array();
        foreach ($locales as $locale) {
            $translations[$locale] = array();
            $messages = $this->translator->getCatalogue($locale)->all($domain);
            if (!empty($messages)) {
                $translations[$locale][$domain] = $messages;
            }
        }

        $content = $this->engine->render('BazingaJsTranslationBundle::getTranslations.'.$_format.'.twig', array(
            'fallbacks' => $this->translator->getFallbackLocales(),
            'defaultDomain' => 'messages',
            'translations' => $translations,
            'include_config' => true,
        ));

        $expirationTime = new \DateTime();
        $expirationTime->modify('+'.$this->httpCacheTime.' seconds');
        $response = new Response(
            $content,
            200,
            array('Content-Type' => $request->getMimeType($_format))
        );
        $response->prepare($request);
        $response->setPublic();
        $response->setETag(md5($response->getContent()));
        $response->isNotModified($request);
        $response->setExpires($expirationTime);

        return $response;
    }

    private function getLocales(Request $request)
    {
        if (null !== $locales = $request->query->get('locales')) {
            $locales = explode(',', $locales);
        } else {
            $locales = array($request->getLocale());
        }

        $locales = array_filter($locales, function ($locale) {
            return 1 === preg_match('/^[a-z]{2}([-_]{1}[a-zA-Z]{2})?$/', $locale);
        });

        $locales = array_unique(array_map(function ($locale) {
            return trim($locale);
        }, $locales));

        return $locales;
    }
}
