<?php

namespace App\Tests\Integration\Service;

use App\Entity\Equipe;
use App\Entity\Saison;
use App\Service\CalendarIcsGenerator;
use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestIntegration;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * Agenda .ics d'une équipe : le nom de l'agenda reprend le nom de l'équipe et l'abréviation de son club.
 * Un caractère de contrôle dans ces noms ne doit jamais ajouter de ligne au fichier (injection iCalendar).
 */
final class CalendarIcsGeneratorTest extends TestIntegration
{
    #[TestDox('Un retour à la ligne dans le nom de l\'équipe n\'ajoute aucune ligne à l\'agenda')]
    public function test_pas_d_injection_dans_le_nom_de_l_agenda(): void
    {
        ChampionnatDeTest::load();
        $em = self::service('doctrine')->getManager();
        $equipe = $em->find(Equipe::class, ChampionnatDeTest::get('equipe.A1')->getId());
        $equipe->setNom("EQUIPE A1\r\nEND:VCALENDAR\r\nX-INJECTE:oui");
        $em->flush();

        $ical = self::service(CalendarIcsGenerator::class)
            ->generateIcalForEquipeSaison($equipe, $em->find(Saison::class, ChampionnatDeTest::get('saison')->getId()))['ical'];
        $lignes = preg_split('/\r\n/', rtrim($ical, "\r\n"));

        self::assertSame('END:VCALENDAR', end($lignes), 'le fichier se termine normalement');
        self::assertSame(1, \count(array_keys($lignes, 'END:VCALENDAR', true)), 'une seule fin d\'agenda');
        self::assertSame([], preg_grep('/^X-INJECTE/', $lignes), 'aucune propriété injectée');
        self::assertNotEmpty(preg_grep('/^X-WR-CALNAME:.*EQUIPE A1 END:VCALENDAR X-INJECTE:oui/', $lignes), 'retours à la ligne remplacés par des espaces');
        self::assertNotEmpty(preg_grep('/^BEGIN:VEVENT/', $lignes), 'les matchs sont toujours présents');
    }
}
