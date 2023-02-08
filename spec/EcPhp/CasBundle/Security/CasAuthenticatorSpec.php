<?php

/**
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/ecphp
 */

declare(strict_types=1);

namespace spec\EcPhp\CasBundle\Security;

use EcPhp\CasBundle\Security\CasAuthenticator;
use EcPhp\CasBundle\Security\Core\User\CasUserProvider;
use EcPhp\CasLib\Cas;
use EcPhp\CasLib\CasInterface;
use EcPhp\CasLib\Introspection\Introspector;
use loophp\UnalteredPsrHttpMessageBridgeBundle\Factory\UnalteredPsrHttpFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PhpSpec\ObjectBehavior;
use Psr\Log\NullLogger;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class CasAuthenticatorSpec extends ObjectBehavior
{
    public function it_can_check_if_authentication_is_supported()
    {
        $this
            ->supports(Request::create('http://app/?ticket=ticket'))
            ->shouldReturn(true);

        $this
            ->supports(Request::create('http://app'))
            ->shouldReturn(false);
    }

    public function it_can_check_if_authentication_is_supported_when_a_user_is_logged_in()
    {
        $cas = $this->getCas();

        $psr17Factory = new Psr17Factory();

        $psrHttpMessageFactory = new PsrHttpFactory(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        $unalteredPsrHttpMessageFactory = new UnalteredPsrHttpFactory($psrHttpMessageFactory, $psr17Factory);
        $userProvider = new CasUserProvider(new Introspector());

        $this
            ->beConstructedWith($cas, $unalteredPsrHttpMessageFactory, $userProvider);

        $this
            ->supports(Request::create('http://app'))
            ->shouldReturn(false);

        $this
            ->supports(Request::create('http://app/?ticket=ticket'))
            ->shouldReturn(true);
    }

    public function it_can_check_the_credentials(UserInterface $user)
    {
        $request = Request::create('https://foo?service=service&ticket=ticket');

        $this
            ->authenticate($request)
            ->shouldBeAnInstanceOf(Passport::class);

        // ticket parameter is missing
        $request = Request::create('https://foo?service=service');

        $this
            ->shouldThrow(AuthenticationException::class)
            ->during('authenticate', [$request]);

        $request = Request::create('https://invalidXml?service=invalid-xml&ticket=ticket');

        $this
            ->shouldThrow(AuthenticationException::class)
            ->during('authenticate', [$request]);
    }

    // TODO: Where to implement this?
    // public function it_can_detect_when_the_request_is_an_ajax_request_and_respond_accordingly()
    // {
    //     $request = new ServerRequest(
    //         'GET',
    //         'http://app/?ticket=ticket',
    //         ['X-Requested-With' => 'XMLHttpRequest']
    //     );

    //     $this
    //         ->start((new HttpFoundationFactory())->createRequest($request))
    //         ->shouldBeAnInstanceOf(JsonResponse::class);
    // }

    public function it_can_get_the_user_from_the_response()
    {
        $request = Request::create('https://foo?service=service&ticket=ticket');

        $this
            ->authenticate($request)
            ->shouldBeAnInstanceOf(Passport::class);

        $request = Request::create('https://foo?service=service&ticket=ticket-failure');

        $this
            ->shouldThrow(AuthenticationException::class)
            ->during('authenticate', [$request]);
    }

    public function it_can_redirect_on_failed_authentication(AuthenticationException $authenticationException)
    {
        $request = Request::create('http://protected-resource/?ticket=ticket');

        $this
            ->onAuthenticationFailure($request, $authenticationException)
            ->shouldBeAnInstanceOf(RedirectResponse::class);

        $this
            ->onAuthenticationFailure($request, $authenticationException)
            ->headers
            ->all()
            ->shouldHaveKeyWithValue('location', ['http://protected-resource/?renew=true']);

        $request = Request::create('http://protected-resource/');

        $this
            ->onAuthenticationFailure($request, $authenticationException)
            ->shouldBeNull();
    }

    public function it_can_redirect_on_success_authentication(TokenInterface $token)
    {
        $request = Request::create('http://app/?param.key=value&ticket=ticket');

        $this
            ->onAuthenticationSuccess($request, $token, 'cas')
            ->shouldBeAnInstanceOf(RedirectResponse::class);

        $this
            ->onAuthenticationSuccess($request, new NullToken('o', 'a'), 'cas')
            ->headers
            ->all()
            ->shouldHaveKeyWithValue('location', ['http://app/?param.key=value']);
    }

    // TODO: Does not exist anymore?
    // public function it_can_redirect_to_the_login_url()
    // {
    //     $request = Request::create('http://app/?ticket=ticket');

    //     $this
    //         ->start($request)
    //         ->shouldBeAnInstanceOf(RedirectResponse::class);

    //     $this
    //         ->start($request)
    //         ->headers
    //         ->all()
    //         ->shouldHaveKeyWithValue('location', ['http://local/cas/login?service=http%3A%2F%2Fapp']);
    // }

    public function it_is_initializable()
    {
        $this->shouldHaveType(CasAuthenticator::class);
    }

    public function let()
    {
        $cas = $this->getCas();

        $psr17Factory = new Psr17Factory();

        $psrHttpMessageFactory = new PsrHttpFactory(
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory
        );

        $unalteredPsrHttpMessageFactory = new UnalteredPsrHttpFactory($psrHttpMessageFactory, $psr17Factory);

        $userProvider = new CasUserProvider(new Introspector());

        $this
            ->beConstructedWith($cas, $unalteredPsrHttpMessageFactory, $userProvider);
    }

    private function getCas(): CasInterface
    {
        $serverRequest = new ServerRequest('GET', 'http://app');
        $properties = \spec\EcPhp\CasBundle\Cas::getTestProperties();
        $client = new Psr18Client(\spec\EcPhp\CasBundle\Cas::getHttpClientMock());
        $cache = new ArrayAdapter();
        $logger = new NullLogger();
        $introspector = new Introspector();

        $psr17Factory = new Psr17Factory();

        return new Cas(
            $serverRequest,
            $properties,
            $client,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $psr17Factory,
            $cache,
            $logger,
            $introspector
        );
    }
}
