<?php

namespace App\Form\Organization;

use App\Entity\Organization\Organization;
use App\Entity\User\User;
use App\Repository\Organization\OrganizationRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Organization picker limited to the organizations the signed-in user belongs to.
 * Administrators can pick any organization.
 */
class MemberOrganizationType extends AbstractType
{
    public function __construct(private readonly Security $security)
    {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Organization::class,
            'choice_label' => 'name',
            'expanded' => false,
            'multiple' => false,
            'query_builder' => function (OrganizationRepository $repository) {
                if ($this->security->isGranted('ROLE_ADMIN')) {
                    return $repository->createQueryBuilder('o');
                }

                $user = $this->security->getUser();
                if (!$user instanceof User) {
                    return $repository->createQueryBuilder('o')->andWhere('1 = 0');
                }

                return $repository->memberQueryBuilder($user);
            },
        ]);
    }

    public function getParent(): string
    {
        return EntityType::class;
    }
}
