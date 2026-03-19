<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use ApiPlatform\Doctrine\Common\State\PersistProcessor;

class UserPasswordHasher implements ProcessorInterface
{
    public function __construct(
        private PersistProcessor $processor,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof User && !empty($data->getPassword())) {
            $data->setPassword(
                $this->passwordHasher->hashPassword($data, $data->getPassword())
            );
            $data->setRoles(['ROLE_USER']);
            $data->setStatus('active');
            $data->setIsVerified(false);
        }
        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}