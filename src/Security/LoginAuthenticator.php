<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use App\Repository\UserRepository;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';
    public const CUSTOMER_LOGIN_ROUTE = 'app_customer_login';
    private const LOGIN_ROUTES = [self::LOGIN_ROUTE, self::CUSTOMER_LOGIN_ROUTE];
    private const STAFF_ONLY_PREFIXES = [
        '/admin',
        '/dashboard',
        '/profile',
        '/product',
        '/category',
        '/stock',
        '/order',
        '/customer',
        '/payment',
    ];

    public function __construct(private UrlGeneratorInterface $urlGenerator, private UserRepository $userRepository)
    {
    }

    /**
     * Only run authentication when the login form is submitted.
     */
    public function supports(Request $request): bool
    {
        $route = $request->attributes->get('_route');

        return in_array($route, self::LOGIN_ROUTES, true)
            && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email', '');

        $request->getSession()->set(
            SecurityRequestAttributes::LAST_USERNAME,
            $email
        );

        return new Passport(
            new UserBadge($email, function (string $userIdentifier) {
                try {
                    return $this->userRepository->findOneBy(['email' => $userIdentifier]);
                } catch (\Throwable $e) {
                    // Convert repository/DB errors into a user-friendly authentication exception
                    throw new CustomUserMessageAuthenticationException('Login temporarily unavailable. Please try again later.');
                }
            }),
            new PasswordCredentials($request->request->get('password', '')),
            [
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): ?Response {
        $user = $token->getUser();

        // ❌ Prevent login if email not verified
        if ($user && method_exists($user, 'isVerified') && !$user->isVerified()) {
            $request->getSession()->getFlashBag()->add(
                'error',
                'You must verify your email before logging in.'
            );

            return new RedirectResponse($this->getLoginUrl($request));
        }

        // Redirect to originally requested page when allowed for the current user
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            if ($this->isTargetPathAllowed($targetPath, $user)) {
                return new RedirectResponse($targetPath);
            }

            $this->removeTargetPath($request->getSession(), $firewallName);
        }

        // Role-based redirects
        if ($user) {
            $roles = $user->getRoles();

            if (in_array('ROLE_ADMIN', $roles, true)) {
                return new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
            }

            if (in_array('ROLE_STAFF', $roles, true)) {
                return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
            }
        }

        // Default: send regular users to landing page
        return new RedirectResponse($this->urlGenerator->generate('app_landing'));
    }

    protected function getLoginUrl(Request $request): string
    {
        $route = $request->attributes->get('_route');

        if ($route === self::CUSTOMER_LOGIN_ROUTE) {
            return $this->urlGenerator->generate(self::CUSTOMER_LOGIN_ROUTE);
        }

        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }

    private function isTargetPathAllowed(string $targetPath, mixed $user): bool
    {
        if (!$user || !method_exists($user, 'getRoles')) {
            return true;
        }

        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_STAFF', $roles, true)) {
            return true;
        }

        $path = parse_url($targetPath, PHP_URL_PATH) ?? $targetPath;
        foreach (self::STAFF_ONLY_PREFIXES as $prefix) {
            if (strpos($path, $prefix) === 0) {
                return false;
            }
        }

        return true;
    }
}