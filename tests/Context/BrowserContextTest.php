<?php

namespace Context;

use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use Elbformat\SymfonyBehatBundle\Browser\State;
use Elbformat\SymfonyBehatBundle\Browser\StateFactory;
use Elbformat\SymfonyBehatBundle\Context\BrowserContext;
use Elbformat\SymfonyBehatBundle\Helper\ArrayDeepCompare;
use Elbformat\SymfonyBehatBundle\Helper\StringCompare;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

class BrowserContextTest extends TestCase
{
    protected ?KernelInterface $kernel = null;
    protected ?BrowserContext $browserContext = null;
    protected ?StateFactory $stateFactory = null;
    protected ?State $state = null;

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(KernelInterface::class);
        $this->state = new State();
        $this->stateFactory = $this->getMockBuilder(StateFactory::class)->getMock();
        $this->stateFactory->method('newState')->willReturn($this->state);
        $this->browserContext = new BrowserContext($this->kernel, $this->stateFactory, __DIR__.'/../..', new StringCompare(), new ArrayDeepCompare());
    }

    public function testIVisit(): void
    {
        $this->kernel->expects($this->once())->method('shutdown');
        $this->kernel->expects($this->once())->method('handle')->with($this->callback(function (Request $request) {
            return '/test' === $request->getPathInfo();
        }))->willReturn(new Response(''));
        $this->browserContext->iVisit('/test');
    }

    public function testINavigateToWithHeaders(): void
    {
        $this->kernel->expects($this->once())->method('shutdown');
        $this->kernel->expects($this->once())->method('handle')->with($this->callback(function (Request $request) {
            if ('/test' !== $request->getPathInfo()) {
                return false;
            }
            if ('de' !== $request->getPreferredLanguage(['en', 'de'])) {
                return false;
            }

            return true;
        }))->willReturn(new Response(''));
        $this->browserContext->iNavigateToWithHeaders('/test', new TableNode([
            0 => [
                'Accept-Language',
                'de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7',
            ],
        ]));
    }

    public function testISendARequestTo(): void
    {
        $postData = [
            '{',
            '  lorem":"ipsum"',
            '}',
        ];

        $this->kernel->expects($this->once())->method('shutdown');
        $this->kernel->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (Request $request) use ($postData) {
                if ('/test' !== $request->getPathInfo()) {
                    return false;
                }
                if ('POST' !== $request->getMethod()) {
                    return false;
                }
                if ('json' !== $request->getContentType()) {
                    return false;
                }
                if (implode("\n", $postData) !== $request->getContent()) {
                    return false;
                }

                return true;
            }))
            ->willReturn(new Response(''));

        $this->browserContext->iSendARequestTo('POST', '/test', new PyStringNode($postData, 1));
    }

    public function testIFollowTheRedirect(): void
    {
        $this->state->update(new Request(), new Response('', 302, ['Location' => '/target']));
        $this->kernel->method('handle')->with($this->callback(function (Request $request) {
            return '/target' === $request->getPathInfo();
        }))->willReturn(new Response('Redirect Target'));
        $this->browserContext->iFollowTheRedirect();
        $this->assertEquals('Redirect Target', $this->state->getResponse()->getContent());
    }

    public function testIFollowTheRedirectQuery(): void
    {
        $this->state->update(Request::create('http://localhost'), new Response('', 302, ['Location' => '?success=true']));
        $this->kernel->method('handle')->with($this->callback(function (Request $request) {
            return '/?success=true' === $request->getRequestUri();
        }))->willReturn(new Response('Redirect Target'));
        $this->browserContext->iFollowTheRedirect();
        $this->assertEquals('Redirect Target', $this->state->getResponse()->getContent());
    }

    public function testIFollowTheRedirectFails(): void
    {
        $this->state->update(Request::create('/'), new Response('', 500));
        $this->expectException(\DomainException::class);
        $this->browserContext->iFollowTheRedirect();
    }

    public function testThePageContainsAFormNamed(): void
    {
        $this->setDom('<form name="hello"></form>');
        $this->browserContext->thePageContainsAFormNamed('hello');
        $this->assertInstanceOf(Form::class, $this->state->getLastForm());
        $this->assertInstanceOf(Crawler::class, $this->state->getLastFormCrawler());
    }

    public function testThePageContainsAFormNamedFail(): void
    {
        $this->setDom('<form name="otherform"></form>');
        $this->expectException(\DomainException::class);
        $this->browserContext->thePageContainsAFormNamed('hello');
    }

    public function testIFillInto(): void
    {
        $crawler = new Crawler('<form><input type="text" name="form[text]"/></form>', 'http://localhost/');
        $this->state->setLastForm($crawler->filterXPath('//form'));
        $this->browserContext->iFillInto('test', 'form[text]');
        $this->assertEquals('test', $this->state->getLastForm()->get('form[text]')->getValue());
    }

    public function testIFillIntoAmbiguous(): void
    {
        $crawler = new Crawler('<form><input name="form[text][]"/><input name="form[text][]"/></form>', 'http://localhost/');
        $this->state->setLastForm($crawler->filterXPath('//form'));
        $this->expectExceptionMessage('form[text] is not a single form field');
        $this->browserContext->iFillInto('test', 'form[text]');
    }

    public function testIFillIntoNoInput(): void
    {
        $crawler = new Crawler('<form></form>', 'http://localhost/');
        $this->state->setLastForm($crawler->filterXPath('//form'));
        $this->expectException(\InvalidArgumentException::class);
        $this->browserContext->iFillInto('test', 'form[text]');
    }

    public function testICheckCheckbox(): void
    {
        $crawler = new Crawler('<form><input type="checkbox" name="form[check]" value="an"/></form>', 'http://localhost/');
        $this->state->setLastForm($crawler->filterXPath('//form'));
        $this->browserContext->iCheckCheckbox('form[check]');
        $this->assertTrue($this->state->getLastForm()->get('form[check]')->hasValue());
    }

    public function testICheckCheckboxWrongType(): void
    {
        $crawler = new Crawler('<form><input type="text" name="form[check]" value="an"/></form>', 'http://localhost/');
        $this->state->setLastForm($crawler->filterXPath('//form'));
        $this->expectExceptionMessage('form[check] is not a choice form field');
        $this->browserContext->iCheckCheckbox('form[check]');
    }

    public function testISelectFrom(): void
    {
        $crawler = new Crawler('<form><select name="form[selection]"><option value="a">Option A</option></select></form>', 'http://localhost/');
        $this->state->setLastForm($crawler->filterXPath('//form'));
        $this->browserContext->iSelectFrom('a', 'form[selection]');
        $this->assertEquals('a', $this->state->getLastForm()->get('form[selection]')->getValue());
    }

    public function testISelectFromMultiple(): void
    {
        $crawler = new Crawler('<form><select name="form[selection]" multiple><option value="a">Option A</option><option value="b">Option B</option></select></form>', 'http://localhost/');
        $this->state->setLastForm($crawler->filterXPath('//form'));
        $this->browserContext->iSelectFrom('a,b', 'form[selection]');
        $this->assertEquals(['a', 'b'], $this->state->getLastForm()->get('form[selection]')->getValue());
    }

    public function testISelectFromNoChoice(): void
    {
        $crawler = new Crawler('<form><input type="text" name="form[selection]" /></form>', 'http://localhost/');
        $this->state->setLastForm($crawler->filterXPath('//form'));
        $this->expectExceptionMessage('form[selection] is not a choice form field');
        $this->browserContext->iSelectFrom('a', 'form[selection]');
    }

    public function testISubmitTheForm(): void
    {
        $dom = '<form action="/submit" method="post"><input type="text" name="lorem" value="ipsum"></form>';
        $this->setDom($dom);
        $crawler = new Crawler($dom, 'http://localhost/');
        $this->state->setLastForm($crawler->filterXPath('//form'));
        $this->kernel->expects($this->once())->method('handle')->with($this->callback(function (Request $request) {
            if ('/submit' !== $request->getRequestUri()) {
                return false;
            }
            if ('POST' !== $request->getMethod()) {
                return false;
            }
            if ('ipsum' !== $request->request->get('lorem')) {
                return false;
            }

            return true;
        }))->willReturn(new Response('Redirect Target'));
        $this->browserContext->iSubmitTheForm();
    }

    public function testISelectUploadAt(): void
    {
        $crawler = new Crawler('<form><input type="file" name="form[file]" /></form>', 'http://localhost/');
        $this->state->setLastForm($crawler->filterXPath('//form'));
        $this->browserContext->iSelectUploadAt('tests/fixtures/1px.jpg', 'form[file]');
        $uplValue = $this->state->getLastForm()->get('form[file]')->getValue();
        $this->assertIsArray($uplValue);
        $this->assertEquals('1px.jpg', $uplValue['name']);
    }

    public function testISelectUploadAtNotAnUpload(): void
    {
        $crawler = new Crawler('<form><input type="text" name="form[file]" /></form>', 'http://localhost/');
        $this->state->setLastForm($crawler->filterXPath('//form'));
        $this->expectExceptionMessage('form[file] is not a file form field');
        $this->browserContext->iSelectUploadAt('tests/fixtures/1px.png', 'form[file]');
    }

    public function testISelectUploadAtMissingFixture(): void
    {
        $crawler = new Crawler('<form><input type="file" name="form[file]" /></form>', 'http://localhost/');
        $this->state->setLastForm($crawler->filterXPath('//form'));
        $this->expectExceptionMessageMatches('/Fixture file not found/');
        $this->expectExceptionMessageMatches('#tests/fixtures/1px.png#');
        $this->browserContext->iSelectUploadAt('tests/fixtures/1px.png', 'form[file]');
    }

    public function testTheResponseStatusCodeIs(): void
    {
        $this->state->update(Request::create('/'), new Response('', 200));
        $this->browserContext->theResponseStatusCodeIs('200');
        $this->expectNotToPerformAssertions();
    }

    public function testTheResponseStatusCodeIsFails(): void
    {
        $this->state->update(Request::create('/'), new Response('', 404));
        $this->expectException(\RuntimeException::class);
        $this->browserContext->theResponseStatusCodeIs('200');
    }

    public function testTheResponseHasHttpHeaders(): void
    {
        $this->state->update(Request::create('/'), new Response('', 200, ['Content-Type' => 'application/json']));
        $table = new TableNode([0 => ['content-type', 'application/json']]);
        $this->browserContext->theResponseHasHttpHeaders($table);
        $this->expectNotToPerformAssertions();
    }

    public function testTheResponseHasHttpHeadersFails(): void
    {
        $this->state->update(Request::create('/'), new Response('', 200, ['Content-Type' => 'text/plain']));
        $table = new TableNode([0 => ['content-type', 'application/json']]);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Header not found or not matching');
        $this->expectExceptionMessage('content-type: text/plain');
        $this->browserContext->theResponseHasHttpHeaders($table);
    }

    public function testIAmBeingRedirectedTo(): void
    {
        $this->state->update(Request::create('/'), new Response('', 302, ['Location' => '/redirecttarget']));
        $this->browserContext->iAmBeingRedirectedTo('/redirecttarget');
        $this->expectNotToPerformAssertions();
    }

    public function testIAmBeingRedirectedToWrongCode(): void
    {
        $this->expectExceptionMessage('Wrong HTTP Code, got 200');
        $this->state->update(Request::create('/'), new Response('', 200));
        $this->browserContext->iAmBeingRedirectedTo('/redirecttarget');
        $this->expectNotToPerformAssertions();
    }

    public function testIAmBeingRedirectedToWrongLocation(): void
    {
        $this->expectExceptionMessage('Wrong redirect target: /anotherone');
        $this->state->update(Request::create('/'), new Response('', 302, ['Location' => '/anotherone']));
        $this->browserContext->iAmBeingRedirectedTo('/redirecttarget');
        $this->expectNotToPerformAssertions();
    }

    public function testIAmBeingRedirectedToNoLocation(): void
    {
        $this->state->update(Request::create('/'), new Response('', 302,));
        $this->expectExceptionMessage('No location header found');
        $this->browserContext->iAmBeingRedirectedTo('/redirecttarget');
    }

    public function testISee(): void
    {
        $this->setDom('<p>Hello World</p>');
        $this->expectNotToPerformAssertions();
        $this->browserContext->iSee('Hello World');
    }

    public function testISeeFails(): void
    {
        $this->setDom('<p>Hello World</p>');
        $this->expectException(\DomainException::class);
        $this->browserContext->iSee('Bye World');
    }

    public function testIDontSee(): void
    {
        $this->setDom('<p>Hello World</p>');
        $this->expectNotToPerformAssertions();
        $this->browserContext->iDontSee('Bye World');
    }

    public function testIDontSeeFails(): void
    {
        $this->setDom('<p>Hello World</p>');
        $this->expectException(\DomainException::class);
        $this->browserContext->iDontSee('Hello World');
    }

    public function testISeeATag(): void
    {
        $this->setDom('<a href="/test"></a>');
        $table = new TableNode([0 => ['href', '/test']]);
        $this->expectNotToPerformAssertions();
        $this->browserContext->iSeeATag('a', $table);
    }

    public function testISeeATagWithContent(): void
    {
        $this->setDom('<a href="/test">Hello World</a>');
        $table = new TableNode([0 => ['href', '/test']]);
        $this->expectNotToPerformAssertions();
        $this->browserContext->iSeeATag('a', $table, 'Hello World');
    }

    public function testISeeATagFails(): void
    {
        $this->setDom('<a href="/test">Hello World</a>');
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tag not found. Did you mean "<a href="/test">Hello World</a>"?');
        $table = new TableNode([0 => ['href', '/notest']]);
        $this->browserContext->iSeeATag('a', $table, 'Hello World');
    }

    public function testISeeATagFailsNoTag(): void
    {
        $this->setDom('<p>Hello World</p>');
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tag not found.');
        $table = new TableNode([0 => ['href', '/notest']]);
        $this->browserContext->iSeeATag('a', $table, 'Hello World');
    }

    public function testISeeATagFailsMultipleAlternatives(): void
    {
        $this->setDom('<p>Hello World</p><p>Bye World</p>');
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Tag with content not found. Did you mean one of the following?\n<p>Hello World</p>\n<p>Bye World</p>");
        $this->browserContext->iSeeATag('p', null, 'Mars');
    }

    public function testISeeATagWrongContent(): void
    {
        $this->setDom('<a href="/test">Hello World</a>');
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tag with content not found. Did you mean "<a href="/test">Hello World</a>"?');
        $table = new TableNode([0 => ['href', '/test']]);
        $this->browserContext->iSeeATag('a', $table, 'Bye World');
    }

    public function testIDontSeeATag(): void
    {
        $this->setDom('<a href="/test">Hello World</a>');
        $this->expectNotToPerformAssertions();
        $table = new TableNode([0 => ['href', '/test']]);
        $this->browserContext->iDontSeeATag('a', $table, 'Bye World');
    }

    public function testIDontSeeATagFails(): void
    {
        $this->setDom('<a href="/test">Hello World</a>');
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tag found');
        $table = new TableNode([0 => ['href', '/test']]);
        $this->browserContext->iDontSeeATag('a', $table, 'Hello World');
    }

    public function testTheFormContainsAnInputField(): void
    {
        $crawler = new Crawler('<form><input type="text" name="form[text]" /></form>', 'http://localhost/');
        $this->state->setLastForm($crawler->filterXPath('//form'));
        $tableData = [
            1 => ['type', 'text'],
            2 => ['name', 'form[text]'],
        ];
        $this->expectNotToPerformAssertions();
        $this->browserContext->theFormContainsAnInputField(new TableNode($tableData));
    }

    public function testTheFormContainsAnInputFieldWrongType(): void
    {
        $crawler = new Crawler('<form><input type="password" name="form[text]"></form>', 'http://localhost/');
        $this->state->setLastForm($crawler->filterXPath('//form'));
        $this->expectExceptionMessage('input not found. Did you mean "<input type="password" name="form[text]"/>"?');
        $tableData = [
            1 => ['type', 'text'],
            2 => ['name', 'form[text]'],
        ];
        $this->browserContext->theFormContainsAnInputField(new TableNode($tableData));
    }

    public function testTheResponseJsonMatches(): void
    {
        $this->setDom('{"hello":"world"}');
        $this->expectNotToPerformAssertions();
        $this->browserContext->theResponseJsonMatches(new PyStringNode(['{"hello":"world"}'], 0));
    }

    public function testTheResponseJsonMatchesFail(): void
    {
        $this->setDom('{"hello":"world"}');
        $this->expectExceptionMessage("{\n    \"hello\": \"world\"\n}\ngoodbye: Missing");
        $this->browserContext->theResponseJsonMatches(new PyStringNode(['{"goodbye":"world"}'], 0));
    }

    protected function setDom(string $dom): void
    {
        $this->state->update(Request::create('/'), new Response($dom, 200));
    }

    public function testTheResponseJsonContains(): void
    {
        $this->setDom('{"hello":"world","number":42}');
        $this->expectNotToPerformAssertions();
        $this->browserContext->theResponseJsonContains(new PyStringNode(['{"hello":"world"}'], 0));
    }

    public function testTheResponseJsonContainsFail(): void
    {
        $this->setDom('{"hello":"world","number":42}');
        $this->expectExceptionMessage("{\n    \"hello\": \"world\",\n    \"number\": 42\n}\ngoodbye: Missing");
        $this->browserContext->theResponseJsonContains(new PyStringNode(['{"goodbye":"world"}'], 0));
    }

    public function testTheResponseJsonContainsNoArray(): void
    {
        $this->setDom('42');
        $this->expectExceptionMessage("Only arrays can contain something. Got integer");
        $this->browserContext->theResponseJsonContains(new PyStringNode(['{"goodbye":"world"}'], 0));
    }

    public function testTheResponseJsonContainsNoArrayExpected(): void
    {
        $this->setDom('{"hello":"world","number":42}');
        $this->expectExceptionMessage("Only arrays can be contained. Got integer");
        $this->browserContext->theResponseJsonContains(new PyStringNode(['42'], 0));
    }

    public function expectNotToPerformAssertions(): void
    {
        // Absolutely NOT RECOMMENDED, but needed workaround for code coverage
        //parent::expectNotToPerformAssertions();
        $this->assertTrue(true);
    }
}
