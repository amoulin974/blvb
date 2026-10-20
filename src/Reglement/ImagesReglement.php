<?php

namespace App\Reglement;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Images insérées dans le règlement depuis l'éditeur (D57, D59) : JPG, PNG ou WebP de 2 Mo au plus (type réel vérifié),
 * nom aléatoire, dossier app.dossier_images_reglement (public/uploads/reglement). Jamais supprimées pour l'instant (D59 :
 * une image citée dans l'historique doit rester).
 */
final class ImagesReglement
{
    public const TAILLE_MAX = 2 * 1024 * 1024;
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(
        #[Autowire('%app.dossier_images_reglement%')]
        private readonly string $dossier,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * Enregistre l'image et rend son nom de fichier
     *
     * @throws \InvalidArgumentException message pour l'admin si le fichier est refusé
     */
    public function enregistrer(UploadedFile $fichier): string
    {
        if (!$fichier->isValid()) {
            throw new \InvalidArgumentException('L\'envoi a échoué, réessayez.');
        }
        if ($fichier->getSize() > self::TAILLE_MAX) {
            throw new \InvalidArgumentException(sprintf('L\'image pèse %s Mo, le maximum est 2 Mo. Réduisez-la avant de l\'envoyer.',
                number_format($fichier->getSize() / 1024 / 1024, 1, ',', '')));
        }
        // Type réel du contenu (pas l'extension ni le type annoncé par le navigateur), et une vraie image
        $type = $fichier->getMimeType();
        if (!isset(self::TYPES[$type]) || @getimagesize($fichier->getPathname()) === false) {
            throw new \InvalidArgumentException('Format non accepté : JPG, PNG ou WebP.');
        }

        $nom = bin2hex(random_bytes(12)).'.'.self::TYPES[$type];
        $this->filesystem->mkdir($this->dossier);
        $fichier->move($this->dossier, $nom);

        return $nom;
    }
}
