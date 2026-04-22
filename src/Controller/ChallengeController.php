<?php

namespace App\Controller;

use App\Entity\Challenge;
use App\Entity\Entry;
use App\Entity\Vote;
use App\Entity\UserBadge;
use App\Entity\User;
use App\Repository\ChallengeRepository;
use App\Repository\EntryRepository;
use App\Repository\VoteRepository;
use App\Repository\UserBadgeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/challenge', name: 'app_challenge_')]
class ChallengeController extends AbstractController
{
    public function __construct(
        private ChallengeRepository $challengeRepo,
        private EntryRepository $entryRepo,
        private VoteRepository $voteRepo,
        private UserBadgeRepository $badgeRepo,
        private EntityManagerInterface $em
    ) {}

    // Main challenge page - list all challenges
    #[Route('', name: 'index')]
    public function index(): Response
    {
        $activeChallenge = $this->challengeRepo->findActiveChallenge();
        $upcomingChallenges = $this->challengeRepo->findUpcomingChallenges();
        $pastChallenges = $this->challengeRepo->findPastChallenges(6);

        return $this->render('challenge/index.html.twig', [
            'activeChallenge' => $activeChallenge,
            'upcomingChallenges' => $upcomingChallenges,
            'pastChallenges' => $pastChallenges,
        ]);
    }

    // View specific challenge details
    #[Route('/{id}', name: 'view', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function view(?Challenge $challenge): Response
    {
        if (!$challenge) {
            $this->addFlash('error', 'Challenge not found.');
            return $this->redirectToRoute('app_challenge_index');
        }

        $entries = $this->entryRepo->findByChallengeOrderedByVotes($challenge);
        $userVotes = [];

        if ($this->getUser()) {
            foreach ($entries as $entry) {
                $vote = $this->voteRepo->findUserVote($this->getUser(), $entry);
                if ($vote) {
                    $userVotes[$entry->getId()] = true;
                }
            }
        }

        return $this->render('challenge/view.html.twig', [
            'challenge' => $challenge,
            'entries' => $entries,
            'userVotes' => $userVotes,
        ]);
    }

    // Submit new entry - shows form
    #[Route('/{id}/submit', name: 'submit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function submit(Request $request, ?Challenge $challenge): Response
    {
        if (!$challenge) {
            $this->addFlash('error', 'Challenge not found.');
            return $this->redirectToRoute('app_challenge_index');
        }
        if (!$challenge->isActive()) {
            $this->addFlash('error', 'Challenge is no longer accepting submissions.');
            return $this->redirectToRoute('app_challenge_view', ['id' => $challenge->getId()]);
        }

        if ($request->isMethod('POST')) {
            $title = $request->request->get('title');
            $description = $request->request->get('description');
            $entryType = $request->request->get('entryType', 'outfit');
            $files = [];

            // Handle file uploads
            if ($request->files->get('files')) {
                $uploadedFiles = $request->files->get('files');
                if (!is_array($uploadedFiles)) {
                    $uploadedFiles = [$uploadedFiles];
                }

                foreach ($uploadedFiles as $file) {
                    if ($file && $file->isValid()) {
                        $extension = $file->getClientOriginalExtension();
                        if (!$extension) {
                            $originalName = $file->getClientOriginalName();
                            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                        }

                        $filename = bin2hex(random_bytes(8));
                        if ($extension) {
                            $filename .= '.' . $extension;
                        }

                        $file->move($this->getParameter('kernel.project_dir') . '/public/uploads/entries', $filename);
                        $files[] = '/uploads/entries/' . $filename;
                    }
                }
            }

            $entry = new Entry();
            $entry->setChallenge($challenge)
                ->setSubmittedBy($this->getUser())
                ->setTitle($title)
                ->setDescription($description)
                ->setEntryType($entryType)
                ->setFiles($files);

            $this->em->persist($entry);
            $this->em->flush();

            $this->addFlash('success', 'Entry submitted successfully! 🎉');
            return $this->redirectToRoute('app_challenge_view', ['id' => $challenge->getId()]);
        }

        return $this->render('challenge/submit.html.twig', [
            'challenge' => $challenge,
        ]);
    }

    // Vote on an entry - AJAX endpoint
    #[Route('/{id}/vote', name: 'vote', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_USER')]
    public function vote(Request $request, Entry $entry): JsonResponse
    {
        $challenge = $entry->getChallenge();

        if (!$challenge->isVotingOpen()) {
            return new JsonResponse(['error' => 'Voting is not open'], 400);
        }

        // Check if user already voted
        $existingVote = $this->voteRepo->findUserVote($this->getUser(), $entry);

        if ($existingVote) {
            $this->em->remove($existingVote);
            $entry->setVoteCount(max(0, $entry->getVoteCount() - 1));
            $voted = false;
        } else {
            $vote = new Vote();
            $vote->setUser($this->getUser())
                ->setEntry($entry);

            $this->em->persist($vote);
            $entry->incrementVoteCount();
            $voted = true;
        }

        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'voted' => $voted,
            'voteCount' => $entry->getVoteCount(),
        ]);
    }

    // View user's creator profile
    #[Route('/creator/{id}', name: 'creator_profile')]
    public function creatorProfile(User $user): Response
    {
        $entries = $this->entryRepo->findByUser($user);
        $badges = $this->badgeRepo->findByUser($user);
        $totalVotes = 0;

        foreach ($entries as $entry) {
            $totalVotes += $entry->getVoteCount();
        }

        return $this->render('challenge/creator_profile.html.twig', [
            'creator' => $user,
            'entries' => $entries,
            'badges' => $badges,
            'totalVotes' => $totalVotes,
        ]);
    }

    // Admin: Create new challenge
    #[Route('/admin/create', name: 'admin_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminCreate(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $challenge = new Challenge();
            $challenge->setTitle($request->request->get('title'))
                ->setDescription($request->request->get('description'))
                ->setTheme($request->request->get('theme'))
                ->setStartDate(new \DateTimeImmutable($request->request->get('startDate')))
                ->setEndDate(new \DateTimeImmutable($request->request->get('endDate')))
                ->setVotingStartDate(new \DateTimeImmutable($request->request->get('votingStartDate')))
                ->setVotingEndDate(new \DateTimeImmutable($request->request->get('votingEndDate')))
                ->setStatus($request->request->get('status', 'active'))
                ->setCreatedBy($this->getUser())
                ->setCategories(array_filter([
                    $request->request->get('category_outfit') ? 'outfit' : null,
                    $request->request->get('category_design') ? 'design' : null,
                ]))
                ->setPrizes([
                    'outfit' => $request->request->get('outfit_prize', ''),
                    'design' => $request->request->get('design_prize', ''),
                ]);

            if ($request->request->get('maxEntries')) {
                $challenge->setMaxEntries((int) $request->request->get('maxEntries'));
            }

            $this->em->persist($challenge);
            $this->em->flush();

            $this->addFlash('success', 'Challenge created successfully!');
            return $this->redirectToRoute('app_challenge_admin_list');
        }

        return $this->render('admin/challenge_form.html.twig', [
            'challenge' => null,
        ]);
    }

    // Admin: Edit challenge
    #[Route('/admin/{id}/edit', name: 'admin_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminEdit(Request $request, Challenge $challenge): Response
    {
        if ($request->isMethod('POST')) {
            $challenge->setTitle($request->request->get('title'))
                ->setDescription($request->request->get('description'))
                ->setTheme($request->request->get('theme'))
                ->setStartDate(new \DateTimeImmutable($request->request->get('startDate')))
                ->setEndDate(new \DateTimeImmutable($request->request->get('endDate')))
                ->setVotingStartDate(new \DateTimeImmutable($request->request->get('votingStartDate')))
                ->setVotingEndDate(new \DateTimeImmutable($request->request->get('votingEndDate')))
                ->setStatus($request->request->get('status'))
                ->setCategories(array_values(array_filter([
                    $request->request->get('category_outfit') ? 'outfit' : null,
                    $request->request->get('category_design') ? 'design' : null,
                ])))
                ->setPrizes([
                    'outfit' => $request->request->get('outfit_prize', ''),
                    'design' => $request->request->get('design_prize', ''),
                ]);

            if ($request->request->get('maxEntries')) {
                $challenge->setMaxEntries((int) $request->request->get('maxEntries'));
            }

            $this->em->flush();

            $this->addFlash('success', 'Challenge updated!');
            return $this->redirectToRoute('app_challenge_admin_list');
        }

        return $this->render('admin/challenge_form.html.twig', [
            'challenge' => $challenge,
        ]);
    }

    // Admin: Delete challenge
    #[Route('/admin/{id}/delete', name: 'admin_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDelete(Request $request, Challenge $challenge): Response
    {
        if ($this->isCsrfTokenValid('delete_challenge_' . $challenge->getId(), $request->request->get('_token'))) {
            $this->em->remove($challenge);
            $this->em->flush();
            $this->addFlash('success', 'Challenge deleted successfully.');
        }
        return $this->redirectToRoute('app_challenge_admin_list');
    }

    // Admin: List all challenges for admin
    #[Route('/admin/list', name: 'admin_list')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminList(): Response
    {
        $challenges = $this->em->getRepository(Challenge::class)
            ->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/challenges_list.html.twig', [
            'challenges' => $challenges,
        ]);
    }

    // Admin: Manage entries for a challenge
    #[Route('/admin/{id}/entries', name: 'admin_entries')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminEntries(Challenge $challenge): Response
    {
        $entries = $this->entryRepo->findByChallengeOrderedByVotes($challenge);

        return $this->render('admin/challenge_entries.html.twig', [
            'challenge' => $challenge,
            'entries' => $entries,
        ]);
    }

    // Admin: Finalize winners and award badges
    #[Route('/admin/{id}/finalize', name: 'admin_finalize', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminFinalize(Request $request, Challenge $challenge): Response
    {
        $entries = $this->entryRepo->findByChallengeOrderedByVotes($challenge);

        $winners = array_slice($entries, 0, 3);

        foreach ($winners as $index => $entry) {
            $rank = $index + 1;
            $entry->setRank($rank);

            // Award badges
            $badgeName = $rank === 1 ? 'challenge_winner' : 'rising_creator';
            $badge = new UserBadge();
            $badge->setUser($entry->getSubmittedBy())
                ->setBadgeName($badgeName)
                ->setDescription("Winner of '{$challenge->getTitle()}'")
                ->setChallengeId($challenge->getId())
                ->setLevel($rank === 1 ? 'gold' : 'bronze');

            $this->em->persist($badge);
        }

        $challenge->setStatus('closed');
        $this->em->flush();

        $this->addFlash('success', 'Challenge finalized! Winners awarded badges.');
        return $this->redirectToRoute('app_challenge_admin_list');
    }

    // Hall of Fame gallery
    #[Route('/hall-of-fame', name: 'hall_of_fame')]
    public function hallOfFame(): Response
    {
        $pastChallenges = $this->challengeRepo->findPastChallenges(20);
        $topEntries = [];

        foreach ($pastChallenges as $challenge) {
            $winners = $this->entryRepo->findTopEntriesByChallenge($challenge, 3);
            $topEntries = array_merge($topEntries, $winners);
        }

        usort($topEntries, fn($a, $b) => $b->getVoteCount() <=> $a->getVoteCount());

        return $this->render('challenge/hall_of_fame.html.twig', [
            'topEntries' => array_slice($topEntries, 0, 50),
        ]);
    }
}
