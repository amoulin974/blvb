<?php

namespace App\VolleyStats;

use App\Entity\MembreEquipe;

/**
 * Désignation d'un joueur sur la page du match, sans numéro de maillot (D66) : prénom et premières lettres du nom,
 * « Camille Ar. », avec autant de lettres qu'il faut pour distinguer deux joueurs de l'équipe.
 */
final class NomsCourts
{
    /**
     * @param iterable<MembreEquipe> $membres
     * @return array<int, string> identifiant du membre => nom court
     */
    public static function pour(iterable $membres): array
    {
        $personnes = [];
        foreach ($membres as $membre) {
            $joueur = $membre->getJoueur();
            $personnes[$membre->getId()] = [trim((string) $joueur?->getPrenom()), trim((string) $joueur?->getNom())];
        }
        $noms = [];
        foreach ($personnes as $id => [$prenom, $nom]) {
            $longueur = min(2, mb_strlen($nom));
            // Allonger tant qu'un autre joueur du même prénom a le même début de nom
            while ($longueur < mb_strlen($nom) && self::homonyme($personnes, $id, $prenom, mb_substr($nom, 0, $longueur))) {
                ++$longueur;
            }
            $debut = mb_substr($nom, 0, $longueur);
            $noms[$id] = trim($prenom.' '.($debut === '' ? '' : $debut.($longueur < mb_strlen($nom) ? '.' : '')));
        }

        return $noms;
    }

    /** @param array<int, array{0: string, 1: string}> $personnes */
    private static function homonyme(array $personnes, int $id, string $prenom, string $debut): bool
    {
        foreach ($personnes as $autre => [$autrePrenom, $autreNom]) {
            if ($autre !== $id && mb_strtolower($autrePrenom) === mb_strtolower($prenom)
                && mb_strtolower(mb_substr($autreNom, 0, mb_strlen($debut))) === mb_strtolower($debut)) {
                return true;
            }
        }

        return false;
    }
}
