<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create an admin user',
)]
class CreateAdminCommand extends Command
{
    private $entityManager;
    private $passwordHasher;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ) {
        // Call parent constructor first
        parent::__construct();
        
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Creates an admin user for VeraBelle Collection')
            ->setHelp('This command creates an admin user with email: admin@verabellecollection.com and password: admin123');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Check if user already exists (by email)
        $existingUser = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['email' => 'admin@verabellecollection.com']);

        if ($existingUser) {
            $io->warning('Admin user already exists!');
            $io->text('Email: admin@verabellecollection.com');
            $io->text('Password: [previously set]');
            return Command::SUCCESS;
        }

        // Create new admin user
        $admin = new User();
        $admin->setEmail('admin@verabellecollection.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setStatus('active');
        
        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin123');
        $admin->setPassword($hashedPassword);
        
        // Mark as verified
        $admin->setIsVerified(true);

        // Save to database
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success('✅ Admin user created successfully!');
        $io->text([
            'Email: <info>admin@verabellecollection.com</info>',
            'Password: <info>admin123</info>',
            '',
            '⚠️  <comment>Important: Change this password after first login!</comment>',
            '',
            'Login URL: http://localhost:8000/login'
        ]);

        return Command::SUCCESS;
    }
}