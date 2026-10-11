<?php

namespace App\Controller\Admin;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Suppression depuis le back-office : vérifie le jeton CSRF, supprime, et informe par un message flash.
 * Si la base refuse (objet encore utilisé ailleurs), la transaction est annulée et un message clair
 * remplace l'erreur 500. La fenêtre de confirmation (admin/_confirmation.html.twig) prévient déjà
 * des cas connus ; ceci couvre le reste.
 */
trait SuppressionTrait
{
    /**
     * @param string        $libelle ex. « l'équipe ARRATS » (repris dans les messages)
     * @param callable|null $avant   préparation exécutée juste avant la suppression (détacher des liens…)
     */
    private function supprimerEntite(Request $request, EntityManagerInterface $em, object $entite, string $tokenId, string $libelle, bool $feminin = false, ?callable $avant = null): bool
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'La page a expiré : rechargez-la puis recommencez. Rien n\'a été supprimé.');

            return false;
        }

        $accord = $feminin ? 'e' : '';
        try {
            if ($avant !== null) {
                $avant();
            }
            $em->remove($entite);
            $em->flush();
        } catch (ForeignKeyConstraintViolationException) {
            $this->addFlash('error', sprintf('%s n\'a pas pu être supprimé%s : des données y sont encore rattachées. Rien n\'a été modifié.', ucfirst($libelle), $accord));

            return false;
        }

        $this->addFlash('success', sprintf('%s a été supprimé%s.', ucfirst($libelle), $accord));

        return true;
    }
}
