<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixture extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new \App\Entity\User();
        $admin->setEmail('admin@gmail.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setStatus('active');
        $admin->setIsVerified(true);
        $hashedPassword = $this->passwordHasher->hashPassword(
            $admin,
            'adminpass'
        );
        $admin->setPassword($hashedPassword);
        $manager->persist($admin);

        $staff = new \App\Entity\User();
    $staff->setEmail('staff@gmail.com');
        $staff->setRoles(['ROLE_STAFF']);
        $staff->setStatus('active');
        $staff->setIsVerified(true);
        $hashedPassword = $this->passwordHasher->hashPassword(  
            $staff,
            'staffpass'
        );
        $staff->setPassword($hashedPassword);
        $manager->persist($staff); 

        $customer = new \App\Entity\User();
        $customer->setEmail('customer@gmail.com');
        $customer->setRoles(['ROLE_CUSTOMER']);
        $customer->setStatus('active');
        $customer->setIsVerified(true);
        $hashedPassword = $this->passwordHasher->hashPassword(
            $customer,
            'customerpass'
        );
        $customer->setPassword($hashedPassword);
        $manager->persist($customer);

        $manager->flush();
    }
}