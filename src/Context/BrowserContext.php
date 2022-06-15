<?php

declare(strict_types=1);

namespace Elbformat\SymfonyBehatBundle\Context;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;
use DOMElement;
use Elbformat\SymfonyBehatBundle\Browser\State;
use Elbformat\SymfonyBehatBundle\Browser\StateFactory;
use Elbformat\SymfonyBehatBundle\Helper\ArrayDeepCompare;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\DomCrawler\Field\FormField;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use function json_decode;

/**
 * This Context is adapted from Mink Context but with less overhead by using the Kernel directly.
 * See https://github.com/Behat/MinkExtension/blob/master/src/Behat/MinkExtension/Context/MinkContext.php for more
 * methods
 *
 * @author Hannes Giesenow <hannes.giesenow@elbformat.de>
 */
class BrowserContext implements Context
{
    protected KernelInterface $kernel;
    protected State $state;
    protected StateFactory $stateFactory;
    protected string $projectDir;

    public function __construct(KernelInterface $kernel, StateFactory $stateFactory, string $projectDir)
    {
        $this->kernel = $kernel;
        $this->projectDir = $projectDir;
        $this->stateFactory = $stateFactory;
        $this->resetState();
    }

    /**
     * @BeforeScenario
     */
    public function resetState(): void
    {
        $this->state = $this->stateFactory->newState();
    }

    /**
     * Opens specified page
     * Example: Given I am on "http://batman.com"
     * Example: And I am on "/articles/isBatmanBruceWayne"
     * Example: When I go to "/articles/isBatmanBruceWayne"
     *
     * @Given /^(?:|I )am on "(?P<page>[^"]+)"$/
     * @When /^(?:|I )go to "(?P<page>[^"]+)"$/
     * @When I navigate to :page
     * @When I visit :page
     */
    public function iVisit(string $page): void
    {
        $this->doRequest(Request::create($page, 'GET', [], $this->state->getCookies()));
    }

    /**
     * @When I send a :method request to :url
     * @When I make a :method request to :url
     */
    public function iSendARequestTo(string $method, string $url, ?PyStringNode $data = null): void
    {
        $server = [];
        if ($data) {
            $server['CONTENT_TYPE'] = 'application/json';
        }
        $this->doRequest(Request::create($url, strtoupper($method), [], $this->state->getCookies(), [], $server, $data ? $data->getRaw() : null));
    }

    /**
     * @When I follow the redirect
     */
    public function iFollowTheRedirect(): void
    {
        $response = $this->state->getResponse();
        $code = $response->getStatusCode();
        if ($code >= 400 || $code < 300) {
            throw new \DomainException('No redirect code found: Code '.$code);
        }
        $targetUrl = (string)$response->headers->get('Location');
        // This is not url, not even a path. Not RFC compliant but we need to handle it either way
        if (0 === strpos($targetUrl, '?')) {
            $targetUrl = $this->state->getRequest()
                    ->getUri().$targetUrl;
        }
        $this->doRequest(Request::create($targetUrl, 'GET', [], $this->state->getCookies()));
    }

    /**
     * @When I use form :name
     * @Then the page contains a form named :name
     */
    public function thePageContainsAFormNamed(string $name): void
    {
        $crawler = $this->getCrawler();
        $form = $crawler->filterXpath(sprintf('//form[@name="%s"]', $name));
        if (!$form->count()) {
            throw $this->createNotFoundException('Form', $crawler->filterXPath('//form'));
        }
        $this->state->setLastForm($form);
    }

    /**
     * @When I fill :value into :name
     * @When I select :name radio button with value :value
     */
    public function iFillInto(string $value, string $name): void
    {
        $formField = $this->state->getLastForm()
            ->get($name);
        if (!$formField instanceof FormField) {
            throw new \DomainException(sprintf('%s is not a single form field.', $name));
        }
        $formField->setValue($value);
    }

    /**
     * @When I check :name checkbox
     */
    public function iCheckCheckbox(string $name): void
    {
        /** @var ChoiceFormField $cb */
        $formField = $this->state->getLastForm()
            ->get($name);
        if (!$formField instanceof ChoiceFormField) {
            throw new \DomainException(sprintf('%s is not a choice form field', $name));
        }
        $formField->tick();
    }

    /**
     * @When I select :value from :name
     */
    public function iSelectFrom(string $value, string $name): void
    {
        $select = $this->state->getLastForm()
            ->get($name);
        if (!$select instanceof ChoiceFormField) {
            throw new \DomainException(sprintf('%s is not a choice form field', $name));
        }
        $select->select($value);
    }

    /**
     * @When I submit the form
     */
    public function iSubmitTheForm(): void
    {
        $form = $this->state->getLastForm();

        $this->doRequest(Request::create($form->getUri(), $form->getMethod(), $form->getPhpValues(), $this->state->getCookies()));
    }

    /**
     * @When I select :fixture upload at :name
     */
    public function iSelectUploadAt(string $fixture, string $name): void
    {
        $field = $this->state->getLastForm()
            ->get($name);
        if (!$field instanceof FileFormField) {
            throw new \DomainException(sprintf('%s is not a file form field', $name));
        }
        if (!file_exists($this->projectDir.'/'.$fixture)) {
            throw new \DomainException(sprintf('Fixture file not found at %s', $this->projectDir.'/'.$fixture));
        }
        $field->upload($this->projectDir.'/'.$fixture);
    }

    /**
     * Checks, that current page response status is equal to specified
     * Example: Then the response status code should be 200
     * Example: And the response status code should be 400
     *
     * @Then /^the response status code is (?P<code>\d+)$/
     * @Then The page shows up
     */
    public function theResponseStatusCodeIs(string $code = '200'): void
    {
        $response = $this->state->getResponse();
        if ($response->getStatusCode() !== (int)$code) {
            throw new \RuntimeException('Received '.$response->getStatusCode());
        }
    }

    /**
     * Checks, that page contains specified text
     * Example: Then I should see "Who is the Batman?"
     * Example: And I should see "Who is the Batman?"
     *
     * @Then I see :text
     */
    public function iSee(string $text): void
    {
        $ex = $this->containsText($text);
        if ($ex) {
            throw $ex;
        }
    }

    /**
     * @Then /^(?:|I )don't see "(?P<text>(?:[^"]|\\")*)"$/
     */
    public function iDontSee(string $text): void
    {
        $ex = $this->containsText($text);
        if (null === $ex) {
            throw new \DomainException('Text found');
        }
    }

    /**
     * @Then I see a(n) :tag tag
     * @Then I see a(n) :tag tag :content
     */
    public function iSeeATag(string $tag, ?TableNode $table = null, ?string $content = null, ?PyStringNode $multiLineContent = null): void
    {
        $ex = $this->mustContainTag($tag, $this->getTableData($table), $multiLineContent ? $multiLineContent->getRaw() : $content);
        if ($ex) {
            throw $ex;
        }
    }

    /**
     * @Then I don't see a(n) :tag tag
     * @Then I don't see a(n) :tag tag :content
     */
    public function idontSeeATag(string $tag, ?TableNode $table = null, ?string $content = null, ?PyStringNode $multiLineContent = null): void
    {
        $ex = $this->mustContainTag($tag, $this->getTableData($table), $multiLineContent ? $multiLineContent->getRaw() : $content);
        if (null === $ex) {
            throw new \DomainException('Tag found');
        }
    }

    /**
     * @Then the form contains an input field
     */
    public function theFormContainsAnInputField(TableNode $attribs): void
    {
        $inputs = $this->state->getLastFormCrawler()
            ->filterXPath('//input');

        /** @var DOMElement $input */
        foreach ($inputs as $input) {
            foreach ($this->getTableData($attribs) as $attrName => $attrVal) {
                if ($input->getAttribute($attrName) !== $attrVal) {
                    continue 2;
                }
            }

            return;
        }

        throw $this->createNotFoundException('input', $inputs);
    }

    /**
     * @Then the response json matches
     */
    public function theResponseJsonMatches(PyStringNode $string): void
    {
        $content = $this->state->getResponse()->getContent() ?: '';
        /** @var mixed $expected */
        $expected = json_decode($string->getRaw(), true, 512, JSON_THROW_ON_ERROR);
        /** @var mixed $got */
        $got = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $dc = new ArrayDeepCompare();
        if ($dc->arrayEquals($expected, $got)) {
            return;
        }
        $gotJson = json_encode($got, JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT);
        throw new \DomainException(sprintf("Got\n%s\n%s", $gotJson, $dc->getDifference()));
    }

    /**
     * @Then the response json contains
     */
    public function theResponseJsonContains(PyStringNode $string): void
    {
        $content = $this->state->getResponse()->getContent() ?: '';
        $expected = json_decode($string->getRaw(), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($expected)) {
            throw new \DomainException(sprintf('Only arrays can be contained. Got %s', gettype($expected)));
        }
        $got = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($got)) {
            throw new \DomainException(sprintf('Only arrays can contain something. Got %s', gettype($got)));
        }
        $dc = new ArrayDeepCompare();
        if ($dc->arrayContains($got, $expected)) {
            return;
        }
        $gotJson = json_encode($got, JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT);
        throw new \DomainException(sprintf("Got\n%s\n%s", $gotJson, $dc->getDifference()));
    }


    /*************/
    /* Internals */
    /*************/

    protected function getCrawler(): Crawler
    {
        return new Crawler((string)$this->state->getResponse()
            ->getContent(), $this->state->getRequest()
            ->getUri());
    }

    protected function containsText(string $text): ?\DomainException
    {
        $regex = '/'.preg_quote($text, '/').'/ui';
        $actual = (string)$this->state->getResponse()
            ->getContent();
        if (!preg_match($regex, $actual)) {
            return new \DomainException('Text not found');
        }

        return null;
    }

    /** @param array<string,string> $attr */
    protected function mustContainTag(string $tagName, array $attr = [], ?string $content = null): ?\DomainException
    {
        $crawler = $this->getCrawler();
        $xPath = '//'.$tagName;
        foreach ($attr as $attrName => $attrVal) {
            $xPath .= sprintf('[@%s="%s"]', $attrName, $attrVal);
        }
        $elements = $crawler->filterXPath($xPath);

        if (!$elements->count()) {
            return $this->createNotFoundException('Tag', $crawler->filterXPath('//'.$tagName));
        }

        // Check content
        if (null !== $content) {
            $content = trim($content);
            /** @var DOMElement $elem */
            foreach ($elements as $elem) {
                if ($content === trim($elem->textContent)) {
                    return null;
                }
            }

            return $this->createNotFoundException('Tag with content', $crawler->filterXPath('//'.$tagName));
        }

        return null;
    }

    protected function doRequest(Request $request): void
    {
        // Reboot kernel
        $this->kernel->shutdown();
        $response = $this->kernel->handle($request);
        $this->state->update($request, $response);
    }

    protected function createNotFoundException(string $what, ?Crawler $fallbacks = null): \DomainException
    {
        $errMsg = sprintf('%s not found.', $what);
        if (null !== $fallbacks) {
            $names = [];
            foreach ($fallbacks as $fallback) {
                $doc = $fallback->ownerDocument;
                $names[] = $doc ? $doc->saveXML($fallback) : '<unknown>';
            }
            switch (\count($names)) {
                case 0:
                    break;
                case 1:
                    $errMsg .= sprintf(' Did you mean "%s"?', $names[0]);
                    break;
                default:
                    $errMsg .= sprintf(" Did you mean one of the following?\n%s", implode("\n", $names));
                    break;
            }
        }

        return new \DomainException($errMsg);
    }

    /**
     * @return array<string,string>
     * @psalm-suppress MixedReturnTypeCoercion
     */
    protected function getTableData(?TableNode $table): array
    {
        if (null === $table) {
            return [];
        }

        return $table->getRowsHash();
    }
}
