<?php

namespace App\Security;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Limite les essais de connexion (D61) : après quelques échecs, l'attente double à chaque nouvel échec.
 *
 * Deux compteurs : adresse saisie + IP, pour qu'un attaquant ne bloque pas le compte d'un autre depuis chez lui, et IP
 * seule, plus tolérant (plusieurs joueurs derrière la même box), contre l'essai d'un mot de passe sur beaucoup de comptes.
 * Pendant l'attente, le mot de passe n'est pas vérifié et l'essai ne compte pas. Une connexion réussie efface le premier
 * compteur seulement : avec son propre compte, un attaquant ne doit pas pouvoir effacer celui de son IP. Une IPv6 compte
 * pour son /64 : un seul abonné en dispose de milliards. Après un changement de mot de passe par e-mail, lever() efface
 * le premier compteur pour toutes les IP.
 *
 * Le compteur est lu puis écrit sans verrou : des essais simultanés peuvent en perdre quelques-uns, sans gain notable
 * pour un attaquant, l'attente s'appliquant dès la première écriture.
 */
final class LimiteurConnexion implements EventSubscriberInterface
{
    /** Nombre d'échecs à partir duquel il faut attendre. */
    private const SEUIL_COMPTE = 3;
    private const SEUIL_IP = 20;

    private const ATTENTE_INITIALE = 30;
    private const ATTENTE_MAX = 86400;

    /** Après la fin de l'attente, durée sans essai au bout de laquelle les échecs sont oubliés. */
    private const OUBLI = 86400;

    /** Plus longue que la vie d'un compteur (attente + oubli, soit 48 h) : un compteur levé ne peut pas revenir. */
    private const VIE_GENERATION = 259200;

    public function __construct(
        private readonly CacheItemPoolInterface $connexionsCache,
        private readonly ClockInterface $clock,
        private readonly RequestStack $requetes,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Avant toute vérification, et avant le jeton CSRF : un essai bloqué ne coûte pas de calcul de mot de passe
            CheckPassportEvent::class => ['verifier', 2080],
            LoginFailureEvent::class => 'compterEchec',
            LoginSuccessEvent::class => 'effacer',
        ];
    }

    /** Attente, en secondes, après $echecs échecs : 30 s au seuil, puis le double à chaque échec, 24 h au plus. */
    public static function attente(int $echecs, int $seuil): int
    {
        if ($echecs < $seuil) {
            return 0;
        }

        return min(self::ATTENTE_MAX, self::ATTENTE_INITIALE * 2 ** min($echecs - $seuil, 20));
    }

    /** « 30 secondes », « 2 minutes », « 1 heure » : arrondi au-dessus, pour ne pas inviter à réessayer trop tôt. */
    public static function dureeEnClair(int $secondes): string
    {
        [$nombre, $unite] = match (true) {
            $secondes < 60 => [$secondes, 'seconde'],
            $secondes < 3600 => [(int) ceil($secondes / 60), 'minute'],
            default => [(int) ceil($secondes / 3600), 'heure'],
        };

        return sprintf('%d %s%s', $nombre, $unite, $nombre > 1 ? 's' : '');
    }

    public function verifier(CheckPassportEvent $event): void
    {
        $identifiant = $this->identifiant($event->getPassport());
        $requete = $this->requetes->getCurrentRequest();
        if ($identifiant === null || $requete === null) {
            return;
        }

        $ip = (string) $requete->getClientIp();
        $finAttente = max($this->lire($this->cleCompte($identifiant, $ip))['jusquA'], $this->lire($this->cleIp($ip))['jusquA']);
        $reste = $finAttente - $this->clock->now()->getTimestamp();
        if ($reste > 0) {
            throw new ConnexionLimitee(sprintf(
                'Trop d\'essais de connexion. Réessayez dans %s. Si vous avez perdu votre mot de passe, cliquez sur « J’ai oublié mon mot de passe », en bas de cette page.',
                self::dureeEnClair($reste)
            ));
        }
    }

    public function compterEchec(LoginFailureEvent $event): void
    {
        // Mot de passe non testé : ni l'attente en cours ni une page expirée ne comptent comme un essai
        $erreur = $event->getException();
        if ($erreur instanceof ConnexionLimitee || $erreur instanceof InvalidCsrfTokenException) {
            return;
        }
        $identifiant = $event->getPassport() === null ? null : $this->identifiant($event->getPassport());
        if ($identifiant === null) {
            return;
        }

        $ip = (string) $event->getRequest()->getClientIp();
        $this->ajouterEchec($this->cleCompte($identifiant, $ip), self::SEUIL_COMPTE);
        $this->ajouterEchec($this->cleIp($ip), self::SEUIL_IP);
    }

    public function effacer(LoginSuccessEvent $event): void
    {
        $identifiant = $this->identifiant($event->getPassport());
        if ($identifiant !== null) {
            $this->connexionsCache->deleteItem($this->cleCompte($identifiant, (string) $event->getRequest()->getClientIp()));
        }
    }

    /** Mot de passe changé par le lien reçu par e-mail : la personne n'a plus à attendre, quelle que soit son IP. */
    public function lever(string $email): void
    {
        $element = $this->connexionsCache->getItem($this->cleGeneration($this->normaliser($email)));
        $element->set(($element->isHit() ? $element->get() : 0) + 1);
        $element->expiresAfter(self::VIE_GENERATION);
        $this->connexionsCache->save($element);
    }

    private function ajouterEchec(string $cle, int $seuil): void
    {
        $echecs = $this->lire($cle)['echecs'] + 1;
        $attente = self::attente($echecs, $seuil);

        $element = $this->connexionsCache->getItem($cle);
        $element->set(['echecs' => $echecs, 'jusquA' => $this->clock->now()->getTimestamp() + $attente]);
        $element->expiresAfter($attente + self::OUBLI);
        $this->connexionsCache->save($element);
    }

    /** @return array{echecs: int, jusquA: int} */
    private function lire(string $cle): array
    {
        $element = $this->connexionsCache->getItem($cle);

        return $element->isHit() ? $element->get() : ['echecs' => 0, 'jusquA' => 0];
    }

    private function cleCompte(string $identifiant, string $ip): string
    {
        $generation = $this->connexionsCache->getItem($this->cleGeneration($identifiant));

        return 'compte_'.hash('sha256', $identifiant.'|'.($generation->isHit() ? $generation->get() : 0).'|'.$this->reseau($ip));
    }

    private function cleIp(string $ip): string
    {
        return 'ip_'.hash('sha256', $this->reseau($ip));
    }

    private function cleGeneration(string $identifiant): string
    {
        return 'generation_'.hash('sha256', $identifiant);
    }

    private function reseau(string $ip): string
    {
        return str_contains($ip, ':') ? IpUtils::anonymize($ip, 0, 8) : $ip;
    }

    /** Adresse saisie, sans tenir compte de la casse ni des espaces : « A@b.fr » et « a@b.fr » partagent leur compteur. */
    private function identifiant(Passport $passport): ?string
    {
        if (!$passport->hasBadge(UserBadge::class)) {
            return null;
        }
        /** @var UserBadge $badge */
        $badge = $passport->getBadge(UserBadge::class);

        return $this->normaliser($badge->getUserIdentifier());
    }

    private function normaliser(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
