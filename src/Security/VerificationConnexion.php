<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;

/**
 * La limitation des essais de connexion (D61) est-elle activable sur ce serveur (D62) ? Elle compte par IP : le site doit
 * voir la vraie IP de chaque visiteur, sans qu'on puisse la lui faire croire. Contrôlé sur une requête du navigateur de
 * l'admin, qui envoie exprès un en-tête X-Forwarded-For forgé (verification_connexion_controller.js).
 */
final class VerificationConnexion
{
    /** Adresse réservée à la documentation (RFC 5737) : aucun visiteur ne l'a */
    public const IP_FORGEE = '203.0.113.77';

    /** Durée de validité d'une vérification réussie pour activer, en secondes */
    public const VALIDITE = 3600;

    public function __construct(
        /** Valeur brute : Request remplace REMOTE_ADDR par l'adresse de l'appelant, invisible ensuite */
        #[Autowire(env: 'default::SYMFONY_TRUSTED_PROXIES')]
        private readonly ?string $proxysConfigures = null,
    ) {
    }

    /** @return list<array{ok: bool, libelle: string, detail: string}> */
    public function controler(Request $requete): array
    {
        return [$this->ipVisiteur($requete), $this->enTeteIgnore($requete), $this->proxys()];
    }

    /** Recontrôlé au moment d'activer : la requête d'activation ne porte pas d'en-tête forgé */
    public function serveurConforme(Request $requete): bool
    {
        return $this->ipVisiteur($requete)['ok'] && $this->proxys()['ok'];
    }

    /** @param list<array{ok: bool}> $controles */
    public static function reussie(array $controles): bool
    {
        return array_filter($controles, fn (array $c) => !$c['ok']) === [];
    }

    /** @return array{ok: bool, libelle: string, detail: string} */
    private function ipVisiteur(Request $requete): array
    {
        $ip = (string) $requete->getClientIp();
        if (!self::publique($ip)) {
            return [
                'ok' => false,
                'libelle' => 'Le site ne voit pas votre adresse IP',
                'detail' => sprintf('Il voit %s, une adresse privée, interne au serveur. Tous les visiteurs compteraient pour un seul : 20 essais ratés bloqueraient tout le monde. Pour l\'hébergeur : renseigner SYMFONY_TRUSTED_PROXIES avec l\'adresse du proxy.', $ip),
            ];
        }
        if ($this->proxyNonDeclare($requete, $ip)) {
            return [
                'ok' => false,
                'libelle' => 'Le site ne voit pas votre adresse IP',
                'detail' => sprintf('Il voit %s, l\'adresse d\'un proxy. Tous les visiteurs compteraient pour un seul : 20 essais ratés bloqueraient tout le monde. Pour l\'hébergeur : déclarer tous les proxys dans SYMFONY_TRUSTED_PROXIES.', $ip),
            ];
        }

        return [
            'ok' => true,
            'libelle' => 'Le site voit votre adresse IP',
            'detail' => sprintf('Adresse vue : %s. Comparez-la à celle qu\'indique une recherche « mon adresse IP » sur Internet. Si elles diffèrent, prévenez l\'hébergeur avant d\'activer.', $ip),
        ];
    }

    /**
     * Une adresse publique reste dans l'en-tête, en dehors de l'adresse forgée, de l'IP vue et des proxys déclarés : le
     * vrai visiteur est plus loin que ce que le site a retenu. L'absence de l'en-tête ne dit rien : un proxy peut l'écraser.
     */
    private function proxyNonDeclare(Request $requete, string $ip): bool
    {
        foreach (explode(',', (string) $requete->headers->get('X-Forwarded-For')) as $adresse) {
            $adresse = trim($adresse);
            if (self::publique($adresse) && $adresse !== self::IP_FORGEE && $adresse !== $ip && !IpUtils::checkIp($adresse, Request::getTrustedProxies())) {
                return true;
            }
        }

        return false;
    }

    private static function publique(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) !== false;
    }

    /** @return array{ok: bool, libelle: string, detail: string} */
    private function enTeteIgnore(Request $requete): array
    {
        return $requete->getClientIp() !== self::IP_FORGEE
            ? [
                'ok' => true,
                'libelle' => 'Une fausse adresse IP est ignorée',
                'detail' => 'Le site a reçu exprès une fausse adresse et ne s\'y est pas fié.',
            ]
            : [
                'ok' => false,
                'libelle' => 'Le site se fie à une fausse adresse IP',
                'detail' => 'N\'importe qui pourrait contourner la limitation en changeant d\'adresse. Pour l\'hébergeur : l\'en-tête X-Forwarded-For est cru ; ne déclarer dans SYMFONY_TRUSTED_PROXIES que le vrai proxy.',
            ];
    }

    /** @return array{ok: bool, libelle: string, detail: string} */
    private function proxys(): array
    {
        $proxys = Request::getTrustedProxies();
        $ok = !IpUtils::checkIp('8.8.8.8', $proxys) && !IpUtils::checkIp('2001:4860:4860::8888', $proxys)
            && stripos((string) $this->proxysConfigures, 'REMOTE_ADDR') === false;

        return $ok
            ? [
                'ok' => true,
                'libelle' => 'Le réglage du serveur est sûr',
                'detail' => 'Le serveur ne fait pas confiance à n\'importe quelle adresse.',
            ]
            : [
                'ok' => false,
                'libelle' => 'Le réglage du serveur n\'est pas sûr',
                'detail' => 'Le serveur fait confiance à trop d\'adresses : n\'importe qui pourrait choisir la sienne. Pour l\'hébergeur : SYMFONY_TRUSTED_PROXIES ne doit contenir que l\'adresse du proxy (ni 0.0.0.0/0, ni ::/0, ni REMOTE_ADDR).',
            ];
    }
}
