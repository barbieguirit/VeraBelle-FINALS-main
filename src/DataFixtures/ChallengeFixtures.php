<?php

namespace App\DataFixtures;

use App\Entity\Challenge;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use DateTimeImmutable;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ChallengeFixtures extends Fixture
{
    private UserPasswordHasherInterface $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Find or create an admin user
        $userRepo = $manager->getRepository(User::class);
        $admin = $userRepo->findOneBy(['email' => 'admin@verabelle.com']);
        
        if (!$admin) {
            $admin = new User();
            $admin->setEmail('admin@verabelle.com');
            $admin->setRoles(['ROLE_ADMIN']);
            $hashedPassword = $this->hasher->hashPassword($admin, 'admin123');
            $admin->setPassword($hashedPassword);
            $manager->persist($admin);
        }

        // Create a sample active challenge
        $challenge1 = new Challenge();
        $challenge1->setTitle('Spring Collection 2026');
        $challenge1->setDescription('Design or style your best spring outfit. Show us how you interpret warm weather fashion.');
        $challenge1->setTheme('Spring Vibes');
        $challenge1->setStartDate(new DateTimeImmutable('2026-03-20'));
        $challenge1->setEndDate(new DateTimeImmutable('2026-04-20'));
        $challenge1->setVotingStartDate(new DateTimeImmutable('2026-04-21'));
        $challenge1->setVotingEndDate(new DateTimeImmutable('2026-05-05'));
        $challenge1->setStatus('active');
        $challenge1->setMaxEntries(100);
        $challenge1->setPrizes([
            '1st' => '₱10,000',
            '2nd' => '₱5,000',
            '3rd' => '₱2,500'
        ]);
        $challenge1->setCreatedBy($admin);
        $manager->persist($challenge1);

        // Create an upcoming challenge
        $challenge2 = new Challenge();
        $challenge2->setTitle('Summer Festival Fashion');
        $challenge2->setDescription('Create looks perfect for summer festivals and outdoor events.');
        $challenge2->setTheme('Festival Ready');
        $challenge2->setStartDate(new DateTimeImmutable('2026-06-01'));
        $challenge2->setEndDate(new DateTimeImmutable('2026-07-01'));
        $challenge2->setVotingStartDate(new DateTimeImmutable('2026-07-02'));
        $challenge2->setVotingEndDate(new DateTimeImmutable('2026-07-15'));
        $challenge2->setStatus('upcoming');
        $challenge2->setMaxEntries(150);
        $challenge2->setPrizes([
            '1st' => '₱15,000',
            '2nd' => '₱7,500',
            '3rd' => '₱3,750'
        ]);
        $challenge2->setCreatedBy($admin);
        $manager->persist($challenge2);

        // Create a past challenge
        $challenge3 = new Challenge();
        $challenge3->setTitle('Winter Elegance');
        $challenge3->setDescription('Showcase your most elegant winter looks.');
        $challenge3->setTheme('Winter Elegance');
        $challenge3->setStartDate(new DateTimeImmutable('2026-01-01'));
        $challenge3->setEndDate(new DateTimeImmutable('2026-02-01'));
        $challenge3->setVotingStartDate(new DateTimeImmutable('2026-02-02'));
        $challenge3->setVotingEndDate(new DateTimeImmutable('2026-02-15'));
        $challenge3->setStatus('closed');
        $challenge3->setMaxEntries(80);
        $challenge3->setPrizes([
            '1st' => '₱8,000',
            '2nd' => '₱4,000',
            '3rd' => '₱2,000'
        ]);
        $challenge3->setCreatedBy($admin);
        $manager->persist($challenge3);

        $manager->flush();
    }
}
