<?php

namespace App\Validator\Constraints;

use App\Util\Slugger;
use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class SlugConstraintValidator extends ConstraintValidator
{
    private $slugger;
    private $userRepository;
    private $security;

    public function __construct(Slugger $slugger, UserRepository $userRepository, Security $security)
    {
        $this->slugger = $slugger;
        $this->userRepository = $userRepository;
        $this->security = $security;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof SlugConstraint) {
            throw new UnexpectedTypeException($constraint, SlugConstraint::class);
        }

        // custom constraints should ignore null and empty values to allow
        // other constraints (NotBlank, NotNull, etc.) take care of that
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            // throw this exception if your validator cannot handle the passed type so that it can be marked as invalid
            throw new UnexpectedValueException($value, 'string');

            // separate multiple types using pipes
            // throw new UnexpectedValueException($value, 'string|int');
        }

        $slug = $this->slugger->sluggify($value);
        $user = $this->security->getUser();

        // On convertit le login saisi en minuscules 
        // pour vérifier qu'il n'existe pas déjà en base
        if ($user && $user->getUsername() == mb_strtolower($value)) {
            // Si le login correspond à l'utilisateur connecté = OK
            $checkMinLogin = false;
        } else {
            // Sinon on vérifie que le login saisi n'est pas déjà présent en base
            $checkMinLogin = $this->userRepository->findBy([
                'username' => mb_strtolower($value)
            ]);
        }

        if ($user && $user->getSlug() == $slug) {
            // Si le slug correspond à l'utilisateur connecté = OK
            $checkSlug = false;
        } else {
            // Sinon, on vérifie la constraint d'unicité concernant le slug en BDD
            $checkSlug = $this->userRepository->checkSlug($slug);
        }

        if ($checkMinLogin || $checkSlug) {
            // the argument must be a string or an object implementing __toString()
            $this->context->buildViolation($constraint->message)
                // ->setParameter('{{ string }}', $value)
                ->addViolation();
        }
    }
}