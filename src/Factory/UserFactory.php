<?php

namespace App\Factory;

use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct(private UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
    }

    #[\Override]
    public static function class(): string
    {
        return User::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    #[\Override]
    protected function defaults(): array|callable
    {
        // Adresses en @blvb.test uniquement (jamais de données réelles) ; mot de passe non utilisable pour se connecter
        // par formulaire : les tests utilisent KernelBrowser::loginUser()
        $prenom = self::faker()->firstName();
        $nom = self::faker()->lastName();

        return [
            'email' => self::faker()->unique()->userName().'@blvb.test',
            'isVerified' => true,
            'nom' => $nom,
            'password' => 'non-utilisable',
            'prenom' => $prenom,
            'roles' => [],
            'telephone' => null,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this
            ->afterInstantiate(function(User $user): void {
                $plainPassword = $user->getPassword();
                if ($plainPassword) {
                    $user->setPassword(
                        $this->passwordHasher->hashPassword($user, $plainPassword)
                    );
                }
            })
        ;
    }
}
