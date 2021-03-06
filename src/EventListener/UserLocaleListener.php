<?php

namespace App\EventListener;

use App\Entity\Language;
use App\Entity\Member;
use App\Entity\Preference;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * @see http://symfony.com/doc/current/cookbook/session/locale_sticky_session.html
 */
class UserLocaleListener
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var EntityManager
     */
    private $em;

    /**
     * UserLocaleListener constructor.
     *
     * @param Session                $session
     * @param EntityManagerInterface $em
     */
    public function __construct(Session $session, EntityManagerInterface $em)
    {
        $this->session = $session;
        $this->em = $em;
    }

    /**
     * @SuppressWarnings(PHPMD.StaticAccess)
     *
     * @param InteractiveLoginEvent $event
     */
    public function onInteractiveLogin(InteractiveLoginEvent $event)
    {
        /** @var Member $user */
        $user = $event->getAuthenticationToken()->getUser();

        // Get preference for locale
        $preferenceRepository = $this->em->getRepository(Preference::class);
        /** @var Preference $preference */
        $preference = $preferenceRepository->findOneBy([
            'codename' => Preference::LOCALE,
        ]);
        $languageId = $user->getMemberPreferenceValue($preference);

        $languageRepository = $this->em->getRepository(Language::class);
        $language = $languageRepository->findOneBy([
            'id' => $languageId,
        ]);
        if ($language) {
            $locale = $language->getShortCode();
        } else {
            $locale = $this->session->get('_locale', 'en');
        }
        \PVars::register('lang', $locale);

        $this->session->set('_locale', $locale);
    }
}
