<?php

namespace App\Tests\Fonctionnel\Securite;

use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\Mailer\Event\MessageEvent;

/** MAI-01 : tous les e-mails partent du compte authentifié sur le SMTP (OVH refuse un autre expéditeur). */
final class ExpediteurEmailTest extends TestFonctionnel
{
    #[TestDox('MAI-01 L\'e-mail « mot de passe oublié » part de blvb@vblc.fr (en-tête From et enveloppe SMTP), objet en français')]
    public function test_expediteur_mot_de_passe_oublie(): void
    {
        $crawler = $this->client->request('GET', '/reset-password');
        $form = $crawler->filter('form')->form();
        $form['reset_password_request_form[email]'] = 'cap.a1@blvb.test';
        $this->client->submit($form);
        self::assertResponseRedirects();

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailAddressContains($email, 'From', 'blvb@vblc.fr');
        self::assertEmailAddressContains($email, 'To', 'cap.a1@blvb.test');
        self::assertSame('BLVB : réinitialisation de votre mot de passe', $email->getSubject());

        /** @var MessageEvent $evenement */
        $evenement = self::getMailerEvents()[0];
        self::assertSame('blvb@vblc.fr', $evenement->getEnvelope()->getSender()->getAddress(), 'expéditeur SMTP (MAIL FROM)');
    }
}
