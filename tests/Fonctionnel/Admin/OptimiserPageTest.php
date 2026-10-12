<?php

namespace App\Tests\Fonctionnel\Admin;

use App\Tests\Support\Story\ChampionnatDeTest;
use App\Tests\Support\TestFonctionnel;
use PHPUnit\Framework\Attributes\TestDox;

final class OptimiserPageTest extends TestFonctionnel
{
    #[TestDox('MAT-07 La page « Optimiser les matchs » affiche la légende des couleurs')]
    public function test_legende(): void
    {
        $this->connecter('admin');
        $this->client->request('GET', '/admin/phase/'.ChampionnatDeTest::get('phase.1')->getId().'/optimiser');

        self::assertResponseIsSuccessful();
        $legende = $this->client->getCrawler()->filter('[aria-label="Légende des tableaux"] li')->each(fn ($li) => $li->text());
        self::assertSame([
            'Dom à domicile',
            'Der derby : même gymnase, pas de déplacement',
            'Ext à l\'extérieur',
            'Ext 2e déplacement de suite (break)',
            '- exempt',
            'gymnase surchargé',
            'créneau non prioritaire ou hors créneau',
        ], $legende);
    }
}
