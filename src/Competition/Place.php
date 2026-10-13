<?php

namespace App\Competition;

/**
 * Place désignée dans une règle de passage entre phases (docs/formats-competition.md, partie G).
 *
 * Deux sortes de places :
 *  - un rang ou une plage de rangs dans une poule : `P1.A#2`, `P1.A#-1` (dernier), `P1.A#1..-3` ;
 *    un rang négatif est compté depuis la fin, pour qu'une même règle vaille pour une poule de 8 et de 9 ;
 *  - l'issue d'un match : `BAR1.AB.vainqueur`, `BAR1.AB.perdant`.
 *
 * Construite par NotationPlace::analyser ; la notation canonique est rendue par __toString.
 */
final class Place
{
    public const VAINQUEUR = 'vainqueur';
    public const PERDANT = 'perdant';

    private function __construct(
        public readonly string $phase,
        public readonly ?string $poule,
        public readonly ?int $rangDebut,
        public readonly ?int $rangFin,
        public readonly ?string $match,
        public readonly ?string $issue,
    ) {
    }

    public static function rang(string $phase, string $poule, int $rangDebut, ?int $rangFin = null): self
    {
        if ($rangDebut === 0 || $rangFin === 0) {
            throw new NotationInvalide('Le rang 0 n\'existe pas : le premier est 1, le dernier est -1.');
        }

        return new self($phase, $poule, $rangDebut, $rangFin ?? $rangDebut, null, null);
    }

    public static function issueDeMatch(string $phase, string $match, string $issue): self
    {
        if (!\in_array($issue, [self::VAINQUEUR, self::PERDANT], true)) {
            throw new NotationInvalide(sprintf('Issue de match inconnue « %s » : vainqueur ou perdant.', $issue));
        }

        return new self($phase, null, null, null, $match, $issue);
    }

    public function estRang(): bool
    {
        return $this->poule !== null;
    }

    public function estPlage(): bool
    {
        return $this->estRang() && $this->rangDebut !== $this->rangFin;
    }

    /**
     * Rangs désignés (1 = premier) dans une poule de $nbEquipes équipes, dans l'ordre du classement.
     *
     * @return list<int>
     * @throws NotationInvalide si un rang sort de la poule ou si la plage est vide
     */
    public function rangsPour(int $nbEquipes): array
    {
        if (!$this->estRang()) {
            throw new \LogicException(sprintf('« %s » désigne l\'issue d\'un match, pas un rang.', $this));
        }

        $debut = $this->rangAbsolu($this->rangDebut, $nbEquipes);
        $fin = $this->rangAbsolu($this->rangFin, $nbEquipes);
        if ($debut > $fin) {
            throw new NotationInvalide(sprintf('« %s » ne désigne aucune équipe dans une poule de %d (du %de au %de).', $this, $nbEquipes, $debut, $fin));
        }

        return range($debut, $fin);
    }

    private function rangAbsolu(int $rang, int $nbEquipes): int
    {
        $absolu = $rang > 0 ? $rang : $nbEquipes + $rang + 1;
        if ($absolu < 1 || $absolu > $nbEquipes) {
            throw new NotationInvalide(sprintf('« %s » : le rang %d n\'existe pas dans une poule de %d équipes.', $this, $rang, $nbEquipes));
        }

        return $absolu;
    }

    public function __toString(): string
    {
        if (!$this->estRang()) {
            return $this->phase.'.'.$this->match.'.'.$this->issue;
        }

        return $this->phase.'.'.$this->poule.'#'.$this->rangDebut.($this->estPlage() ? '..'.$this->rangFin : '');
    }
}
