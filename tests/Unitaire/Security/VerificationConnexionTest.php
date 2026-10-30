<?php

namespace App\Tests\Unitaire\Security;

use App\Security\VerificationConnexion;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;

/**
 * PAR-03 : contrôles de la vérification (D62), selon les proxys de confiance du serveur.
 */
final class VerificationConnexionTest extends TestCase
{
    private array $proxys;
    private int $enTetes;

    protected function setUp(): void
    {
        $this->proxys = Request::getTrustedProxies();
        $this->enTetes = Request::getTrustedHeaderSet();
    }

    protected function tearDown(): void
    {
        Request::setTrustedProxies($this->proxys, $this->enTetes);
    }

    /** @return list<bool> IP publique, en-tête ignoré, proxys limités */
    private function resultats(string $ipServeur, string $xForwardedFor, array $proxys = [], ?string $configures = null): array
    {
        Request::setTrustedProxies($proxys, Request::HEADER_X_FORWARDED_FOR);
        $requete = Request::create('/', 'POST', server: ['REMOTE_ADDR' => $ipServeur, 'HTTP_X_FORWARDED_FOR' => $xForwardedFor]);

        return array_column((new VerificationConnexion($configures))->controler($requete), 'ok');
    }

    #[TestDox('PAR-03 : derrière un proxy bien déclaré, la vérification réussit, même avec une adresse inventée dans l\'en-tête')]
    public function test_proxy_bien_declare(): void
    {
        self::assertSame([true, true, true], $this->resultats('10.0.0.1', VerificationConnexion::IP_FORGEE.', 8.8.8.8', ['10.0.0.1']));
    }

    #[TestDox('PAR-03 : proxy non déclaré : le site voit une adresse privée, partagée par tous les visiteurs')]
    public function test_proxy_non_declare(): void
    {
        self::assertSame([false, true, true], $this->resultats('10.0.0.1', VerificationConnexion::IP_FORGEE.', 8.8.8.8'));
        self::assertSame([false, true, true], $this->resultats('100.64.0.1', ''), 'adresse partagée d\'opérateur');
    }

    #[TestDox('PAR-03 : toutes les adresses déclarées comme proxy : l\'adresse inventée est crue, la vérification échoue')]
    public function test_tout_internet_declare(): void
    {
        [, $enTete, $proxys] = $this->resultats('8.8.8.8', VerificationConnexion::IP_FORGEE, ['0.0.0.0/0']);

        self::assertFalse($enTete);
        self::assertFalse($proxys);
    }

    #[TestDox('PAR-03 : proxy non déclaré à adresse publique, ou un seul proxy déclaré sur deux : la vérification échoue')]
    public function test_proxy_public_non_declare(): void
    {
        self::assertFalse($this->resultats('51.1.1.1', VerificationConnexion::IP_FORGEE.', 8.8.8.8')[0], 'en-tête complété');
        self::assertFalse($this->resultats('51.1.1.1', '8.8.8.8')[0], 'en-tête écrasé');
        self::assertFalse($this->resultats('10.0.0.1', VerificationConnexion::IP_FORGEE.', 8.8.8.8, 51.1.1.1', ['10.0.0.1'])[0], 'deux proxys');
    }

    #[TestDox('PAR-03 : accès direct, adresse forgée seule, ou en-tête écrasé par un proxy déclaré : le contrôle passe')]
    public function test_acces_sans_proxy(): void
    {
        self::assertSame([true, true, true], $this->resultats('8.8.8.8', VerificationConnexion::IP_FORGEE));
        self::assertSame([true, true, true], $this->resultats('8.8.8.8', ''));
        self::assertSame([true, true, true], $this->resultats('10.0.0.1', '8.8.8.8', ['10.0.0.1']));
        self::assertSame([true, true, true], $this->resultats('8.8.8.8', '10.1.2.3'), 'proxy privé d\'entreprise');
    }

    #[TestDox('PAR-03 : REMOTE_ADDR et ::/0 sont refusés, même écrits autrement ; les réseaux privés sont acceptés')]
    public function test_valeurs_de_proxys(): void
    {
        self::assertFalse($this->resultats('10.0.0.1', '8.8.8.8', ['10.0.0.1'], 'REMOTE_ADDR')[2]);
        self::assertFalse($this->resultats('8.8.8.8', '', ['0::/0'])[2]);
        self::assertSame([true, true, true], $this->resultats('10.0.0.1', '8.8.8.8', IpUtils::PRIVATE_SUBNETS));
    }

    #[TestDox('PAR-03 : la vérification ne réussit que si tous les contrôles réussissent')]
    public function test_reussie(): void
    {
        self::assertTrue(VerificationConnexion::reussie([['ok' => true], ['ok' => true]]));
        self::assertFalse(VerificationConnexion::reussie([['ok' => true], ['ok' => false]]));
    }
}
