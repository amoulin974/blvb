<?php

namespace App\Competition\Sport;

use App\Entity\Saison;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Sports proposés (services RegleSport) et sport d'une saison (Saison::getSport, volley-ball par défaut). */
class Sports
{
    /** @var array<string, RegleSport> */
    private array $parCode = [];

    /** @param iterable<RegleSport> $sports */
    public function __construct(
        #[AutowireIterator(RegleSport::TAG)] iterable $sports = [new Volley()],
    ) {
        foreach ($sports as $sport) {
            $this->parCode[$sport->code()] = $sport;
        }
    }

    public function pourSaison(?Saison $saison): RegleSport
    {
        return $this->parCode($saison?->getSport() ?? Volley::CODE);
    }

    /** Sport de ce code ; volley-ball si le code est inconnu (un sport retiré ne doit pas casser une saison archivée). */
    public function parCode(string $code): RegleSport
    {
        return $this->parCode[$code] ?? $this->parCode[Volley::CODE] ?? new Volley();
    }

    /** @return array<string, string> code => libellé, pour un choix dans le wizard */
    public function liste(): array
    {
        return array_map(fn (RegleSport $s) => $s->libelle(), $this->parCode);
    }
}
