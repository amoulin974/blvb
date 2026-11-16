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
     * @throws NotationInvalide si un rang sort de la poule ; PlageVide si la plage est vide
     */
    public function rangsPour(int $nbEquipes): array
    {
        if (!$this->estRang()) {
            throw new \LogicException(sprintf('« %s » désigne le résultat d\'un match, pas un rang.', $this));
        }

        $debut = $this->rangAbsolu($this->rangDebut, $nbEquipes);
        $fin = $this->rangAbsolu($this->rangFin, $nbEquipes);
        if ($debut > $fin) {
            throw new PlageVide(sprintf('aucune équipe concernée dans une poule de %d (du %de au %de).', $nbEquipes, $debut, $fin));
        }

        return range($debut, $fin);
    }

    private function rangAbsolu(int $rang, int $nbEquipes): int
    {
        $absolu = $rang > 0 ? $rang : $nbEquipes + $rang + 1;
        if ($absolu < 1 || $absolu > $nbEquipes) {
            throw new NotationInvalide(sprintf('la poule n\'a que %d équipe%s : il n\'y a pas de %s.', $nbEquipes, $nbEquipes > 1 ? 's' : '', self::rangEnClair($rang)));
        }

        return $absolu;
    }

    /**
     * La place en clair, pour l'administrateur : « 1er et 2e », « du 3e à l'antépénultième », « dernier »,
     * « toutes les équipes », « vainqueur du match AB » (sans la poule ni la phase).
     */
    public function enClair(): string
    {
        if (!$this->estRang()) {
            return sprintf('%s du match %s', $this->issue, $this->match);
        }
        if (!$this->estPlage()) {
            return self::rangEnClair($this->rangDebut);
        }
        if ($this->rangDebut === 1 && $this->rangFin === -1) {
            return 'toutes les équipes';
        }
        if (($this->rangDebut > 0 && $this->rangFin === $this->rangDebut + 1) || ($this->rangDebut < 0 && $this->rangFin === $this->rangDebut + 1)) {
            return self::rangEnClair($this->rangDebut).' et '.self::rangEnClair($this->rangFin);
        }
        $fin = self::rangEnClair($this->rangFin);

        return 'du '.self::rangEnClair($this->rangDebut).(preg_match('/^[aeiouyéèê]/u', $fin) ? ' à l\'' : ' au ').$fin;
    }

    /** 1 → « 1er », 3 → « 3e », -1 → « dernier », -2 → « avant-dernier », -3 → « antépénultième », -5 → « 5e en partant de la fin » */
    public static function rangEnClair(int $rang): string
    {
        return match (true) {
            $rang === 1 => '1er',
            $rang > 1 => $rang.'e',
            $rang === -1 => 'dernier',
            $rang === -2 => 'avant-dernier',
            $rang === -3 => 'antépénultième',
            default => (-$rang).'e en partant de la fin',
        };
    }

    public function __toString(): string
    {
        if (!$this->estRang()) {
            return $this->phase.'.'.$this->match.'.'.$this->issue;
        }

        return $this->phase.'.'.$this->poule.'#'.$this->rangDebut.($this->estPlage() ? '..'.$this->rangFin : '');
    }
}
